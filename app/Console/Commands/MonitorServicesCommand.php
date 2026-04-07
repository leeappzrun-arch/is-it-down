<?php

namespace App\Console\Commands;

use App\Mail\ServiceSslExpiryWarningMail;
use App\Mail\ServiceStatusChangedMail;
use App\Mail\WebhookDeliveryFailedMail;
use App\Models\Recipient;
use App\Models\Service;
use App\Models\User;
use App\Support\Monitoring\ServiceCheckResult;
use App\Support\Monitoring\ServiceMonitor;
use App\Support\Monitoring\SslCertificateInspectionResult;
use App\Support\Monitoring\SslCertificateInspector;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MonitorServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:services';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check due services and send monitoring notifications when their status changes.';

    /**
     * Execute the console command.
     */
    public function handle(ServiceMonitor $serviceMonitor, SslCertificateInspector $sslCertificateInspector): int
    {
        $dueServices = Service::query()
            ->with([
                'recipients:id,name,endpoint,webhook_auth_type,webhook_auth_username,webhook_auth_password,webhook_auth_token,webhook_auth_header_name,webhook_auth_header_value',
                'recipientGroups:id,name',
                'recipientGroups.recipients:id,name,endpoint,webhook_auth_type,webhook_auth_username,webhook_auth_password,webhook_auth_token,webhook_auth_header_name,webhook_auth_header_value',
                'groups:id,name',
                'groups.recipients:id,name,endpoint,webhook_auth_type,webhook_auth_username,webhook_auth_password,webhook_auth_token,webhook_auth_header_name,webhook_auth_header_value',
                'groups.recipientGroups:id,name',
                'groups.recipientGroups.recipients:id,name,endpoint,webhook_auth_type,webhook_auth_username,webhook_auth_password,webhook_auth_token,webhook_auth_header_name,webhook_auth_header_value',
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->orderBy('id')
            ->get();

        foreach ($dueServices as $service) {
            $this->monitorService($service, $serviceMonitor, $sslCertificateInspector);
        }

        $this->info('Checked '.$dueServices->count().' service(s).');

        return self::SUCCESS;
    }

    /**
     * Monitor the given service and deliver any required notifications.
     */
    private function monitorService(Service $service, ServiceMonitor $serviceMonitor, SslCertificateInspector $sslCertificateInspector): void
    {
        $checkedAt = now();
        $previousStatus = $service->current_status;
        $result = $serviceMonitor->check($service);
        $downtimeDurationSeconds = $this->downtimeDurationSeconds($service, $previousStatus, $result->status, $checkedAt);
        $downtimeDurationSummary = $this->downtimeDurationSummary($service, $previousStatus, $result->status, $checkedAt);

        $statusChanged = $previousStatus !== $result->status;

        $service->forceFill([
            'current_status' => $result->status,
            'last_response_code' => $result->responseCode,
            'last_check_reason' => $result->reason,
            'last_checked_at' => $checkedAt,
            'next_check_at' => $checkedAt->copy()->addSeconds($service->interval_seconds),
            'last_status_changed_at' => $statusChanged ? $checkedAt : $service->last_status_changed_at,
        ])->save();

        if (! $this->shouldNotifyRecipients($previousStatus, $result)) {
            $this->notifySslExpiryIfNeeded($service, $sslCertificateInspector, $checkedAt);

            return;
        }

        $webhookFailures = [];

        foreach ($service->effectiveRecipientRoutes() as $route) {
            /** @var Recipient $recipient */
            $recipient = $route['recipient'];

            if ($recipient->isMailEndpoint()) {
                Mail::to($recipient->endpointTarget())->send(new ServiceStatusChangedMail(
                    service: $service,
                    currentStatus: $result->status,
                    previousStatus: $previousStatus,
                    reason: $result->reason,
                    responseCode: $result->responseCode,
                    checkedAt: $checkedAt,
                    downtimeDurationSummary: $downtimeDurationSummary,
                ));

                continue;
            }

            $failureReason = $this->deliverWebhookNotification(
                recipient: $recipient,
                payload: $this->buildWebhookPayload(
                    service: $service,
                    result: $result,
                    previousStatus: $previousStatus,
                    checkedAt: $checkedAt,
                    downtimeDurationSeconds: $downtimeDurationSeconds,
                    downtimeDurationSummary: $downtimeDurationSummary,
                ),
            );

            if ($failureReason === null) {
                continue;
            }

            $webhookFailures[] = [
                'recipient_name' => $recipient->name,
                'webhook_url' => $recipient->webhookUrl(),
                'reason' => $failureReason,
                'authentication' => $recipient->webhookAuthenticationSummary(),
                'sources' => $route['sources'],
            ];
        }

        if ($webhookFailures !== []) {
            $this->sendWebhookFailureAlert($service, $result, $webhookFailures, $checkedAt);
        }

        $this->notifySslExpiryIfNeeded($service, $sslCertificateInspector, $checkedAt);
    }

    /**
     * Determine whether recipients should be notified for this result.
     */
    private function shouldNotifyRecipients(?string $previousStatus, ServiceCheckResult $result): bool
    {
        if ($result->status === Service::STATUS_DOWN) {
            return $previousStatus !== Service::STATUS_DOWN;
        }

        return $previousStatus === Service::STATUS_DOWN;
    }

    /**
     * Build the JSON payload sent to webhook recipients.
     *
     * @return array<string, mixed>
     */
    private function buildWebhookPayload(
        Service $service,
        ServiceCheckResult $result,
        ?string $previousStatus,
        CarbonInterface $checkedAt,
        ?int $downtimeDurationSeconds,
        ?string $downtimeDurationSummary,
    ): array {
        $payload = [
            'event' => 'service.status_changed',
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'url' => $service->url,
                'interval_seconds' => $service->interval_seconds,
                'expectation' => $service->hasExpectation()
                    ? [
                        'type' => $service->expect_type,
                        'value' => $service->expect_value,
                    ]
                    : null,
            ],
            'status' => $result->status,
            'previous_status' => $previousStatus,
            'checked_at' => $checkedAt->toIso8601String(),
            'response_code' => $result->responseCode,
            'reason' => $result->reason,
        ];

        if ($downtimeDurationSummary !== null) {
            $payload['downtime_duration'] = [
                'seconds' => $downtimeDurationSeconds,
                'human' => $downtimeDurationSummary,
            ];
        }

        return $payload;
    }

    /**
     * Notify recipients when a service certificate is expiring soon.
     */
    private function notifySslExpiryIfNeeded(
        Service $service,
        SslCertificateInspector $sslCertificateInspector,
        CarbonInterface $checkedAt,
    ): void {
        if (! $service->ssl_expiry_notifications_enabled || ! $service->usesHttps()) {
            return;
        }

        if (
            $service->last_ssl_expiry_notification_sent_at !== null
            && $service->last_ssl_expiry_notification_sent_at->copy()->addDay()->isFuture()
        ) {
            return;
        }

        $certificate = $sslCertificateInspector->inspect($service);

        if (! $certificate instanceof SslCertificateInspectionResult || ! $certificate->expiresWithinDays(10, $checkedAt)) {
            return;
        }

        $webhookFailures = [];
        $effectiveRecipientRoutes = $service->effectiveRecipientRoutes();

        if ($effectiveRecipientRoutes->isEmpty()) {
            return;
        }

        foreach ($effectiveRecipientRoutes as $route) {
            /** @var Recipient $recipient */
            $recipient = $route['recipient'];

            if ($recipient->isMailEndpoint()) {
                Mail::to($recipient->endpointTarget())->send(new ServiceSslExpiryWarningMail(
                    service: $service,
                    certificate: $certificate,
                    checkedAt: $checkedAt,
                ));

                continue;
            }

            $failureReason = $this->deliverWebhookNotification(
                recipient: $recipient,
                payload: $this->buildSslExpiryWebhookPayload($service, $certificate, $checkedAt),
            );

            if ($failureReason === null) {
                continue;
            }

            $webhookFailures[] = [
                'recipient_name' => $recipient->name,
                'webhook_url' => $recipient->webhookUrl(),
                'reason' => $failureReason,
                'authentication' => $recipient->webhookAuthenticationSummary(),
                'sources' => $route['sources'],
            ];
        }

        $service->forceFill([
            'last_ssl_expiry_notification_sent_at' => $checkedAt,
        ])->save();

        if ($webhookFailures !== []) {
            $this->sendWebhookFailureAlert($service, 'ssl_expiring', $webhookFailures, $checkedAt);
        }
    }

    /**
     * Build the JSON payload sent to webhook recipients for SSL expiry warnings.
     *
     * @return array<string, mixed>
     */
    private function buildSslExpiryWebhookPayload(
        Service $service,
        SslCertificateInspectionResult $certificate,
        CarbonInterface $checkedAt,
    ): array {
        return [
            'event' => 'service.ssl_expiring',
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'url' => $service->url,
                'interval_seconds' => $service->interval_seconds,
                'expectation' => $service->hasExpectation()
                    ? [
                        'type' => $service->expect_type,
                        'value' => $service->expect_value,
                    ]
                    : null,
            ],
            'checked_at' => $checkedAt->toIso8601String(),
            'ssl' => [
                'expires_at' => $certificate->expiresAt->toIso8601String(),
                'days_until_expiry' => $certificate->daysUntilExpiry($checkedAt),
                'summary' => $certificate->summary($checkedAt),
            ],
        ];
    }

    /**
     * Get the downtime duration in seconds for recovery notifications.
     */
    private function downtimeDurationSeconds(
        Service $service,
        ?string $previousStatus,
        string $currentStatus,
        CarbonInterface $checkedAt,
    ): ?int {
        if ($previousStatus !== Service::STATUS_DOWN || $currentStatus !== Service::STATUS_UP) {
            return null;
        }

        return $service->statusDurationInSeconds($checkedAt);
    }

    /**
     * Get the downtime duration summary for recovery notifications.
     */
    private function downtimeDurationSummary(
        Service $service,
        ?string $previousStatus,
        string $currentStatus,
        CarbonInterface $checkedAt,
    ): ?string {
        if ($previousStatus !== Service::STATUS_DOWN || $currentStatus !== Service::STATUS_UP) {
            return null;
        }

        return $service->statusDurationSummary($checkedAt);
    }

    /**
     * Deliver a webhook notification and return the failure reason when it fails.
     */
    private function deliverWebhookNotification(Recipient $recipient, array $payload): ?string
    {
        try {
            $request = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10);

            $request = match ($recipient->webhook_auth_type) {
                Recipient::WEBHOOK_AUTH_BASIC => $request->withBasicAuth(
                    (string) $recipient->webhook_auth_username,
                    (string) $recipient->webhook_auth_password,
                ),
                Recipient::WEBHOOK_AUTH_BEARER => $request->withToken((string) $recipient->webhook_auth_token),
                Recipient::WEBHOOK_AUTH_HEADER => $request->withHeaders([
                    (string) $recipient->webhook_auth_header_name => (string) $recipient->webhook_auth_header_value,
                ]),
                default => $request,
            };

            $response = $request->post($recipient->webhookUrl(), $payload);
        } catch (Throwable $throwable) {
            return 'Webhook request failed: '.trim($throwable->getMessage());
        }

        if ($response->successful()) {
            return null;
        }

        return 'Webhook responded with HTTP '.$response->status().'.';
    }

    /**
     * Send a webhook failure email to all admin users.
     *
     * @param  array<int, array{recipient_name: string, webhook_url: string, reason: string, authentication: string, sources: array<int, string>}>  $webhookFailures
     */
    private function sendWebhookFailureAlert(
        Service $service,
        ServiceCheckResult|string $result,
        array $webhookFailures,
        CarbonInterface $checkedAt,
    ): void {
        $adminEmails = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        if ($adminEmails === []) {
            return;
        }

        Mail::to($adminEmails)->send(new WebhookDeliveryFailedMail(
            service: $service,
            triggeredStatus: $result instanceof ServiceCheckResult ? $result->status : $result,
            failures: $webhookFailures,
            checkedAt: $checkedAt,
        ));
    }
}
