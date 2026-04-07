<?php

namespace Tests\Feature;

use App\Mail\ServiceSslExpiryWarningMail;
use App\Mail\ServiceStatusChangedMail;
use App\Mail\WebhookDeliveryFailedMail;
use App\Models\Recipient;
use App\Models\Service;
use App\Models\User;
use App\Support\Monitoring\SslCertificateInspectionResult;
use App\Support\Monitoring\SslCertificateInspector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class ServiceMonitoringCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_command_sends_configured_additional_headers_with_the_service_check(): void
    {
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 10:20:00');

        $this->travelTo($checkedAt);

        $service = Service::factory()->create([
            'name' => 'Marketing Site',
            'url' => 'https://status.example.com',
            'additional_headers' => [
                ['name' => 'X-Monitor', 'value' => 'is-it-down'],
            ],
        ]);

        Http::fake([
            'https://status.example.com' => function ($request) {
                $this->assertSame('is-it-down', $request->header('X-Monitor')[0] ?? null);

                return Http::response('All systems operational', 200);
            },
        ]);

        $this->artisan('monitor:services')->assertSuccessful();
    }

    public function test_monitoring_command_marks_services_down_for_expectation_failures_and_does_not_repeat_the_same_down_alert(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 10:30:00');

        $this->travelTo($checkedAt);

        $recipient = Recipient::factory()->mail()->create([
            'endpoint' => 'mailto://ops@example.com',
        ]);

        $service = Service::factory()->expectsText()->create([
            'name' => 'Marketing Site',
            'url' => 'https://status.example.com',
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
        ]);

        $service->recipients()->sync([$recipient->id]);

        Http::fake([
            'https://status.example.com' => Http::response('Everything is broken', 200),
        ]);

        $this->artisan('monitor:services')->assertSuccessful();

        $service->refresh();

        $this->assertSame(Service::STATUS_DOWN, $service->current_status);
        $this->assertSame('Response body did not contain the expected text.', $service->last_check_reason);
        $this->assertSame(200, $service->last_response_code);
        $this->assertSame($checkedAt->addMinute()->toDateTimeString(), $service->next_check_at?->toDateTimeString());

        Mail::assertSent(ServiceStatusChangedMail::class, function (ServiceStatusChangedMail $mail) use ($service): bool {
            return $mail->hasTo('ops@example.com')
                && $mail->service->is($service)
                && $mail->currentStatus === Service::STATUS_DOWN;
        });

        $this->travelTo($checkedAt->addMinute()->addSecond());

        $service->forceFill([
            'next_check_at' => now()->subSecond(),
        ])->save();

        Http::fake([
            'https://status.example.com' => Http::response('Everything is still broken', 200),
        ]);

        $this->artisan('monitor:services')->assertSuccessful();

        $service->refresh();

        $this->assertSame(Service::STATUS_DOWN, $service->current_status);
        $this->assertSame('Response body did not contain the expected text.', $service->last_check_reason);
        $this->assertSame($checkedAt->addMinutes(2)->addSecond()->toDateTimeString(), $service->next_check_at?->toDateTimeString());

        Mail::assertSent(ServiceStatusChangedMail::class, 1);
    }

    public function test_monitoring_command_sends_a_recovery_email_when_a_down_service_recovers(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 10:45:00');

        $this->travelTo($checkedAt);

        $recipient = Recipient::factory()->mail()->create([
            'endpoint' => 'mailto://ops@example.com',
        ]);

        $service = Service::factory()->expectsText()->create([
            'name' => 'Marketing Site',
            'url' => 'https://status.example.com',
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
            'current_status' => Service::STATUS_DOWN,
            'last_check_reason' => 'Response body did not contain the expected text.',
            'last_checked_at' => $checkedAt->subMinute(),
            'next_check_at' => $checkedAt->subSecond(),
            'last_status_changed_at' => $checkedAt->subMinute(),
        ]);

        $service->recipients()->sync([$recipient->id]);

        Http::fake([
            'https://status.example.com' => Http::response('All systems operational', 200),
        ]);

        $this->artisan('monitor:services')->assertSuccessful();

        $service->refresh();

        $this->assertSame(Service::STATUS_UP, $service->current_status);
        $this->assertSame('Received an HTTP 200 response and the expected text was present.', $service->last_check_reason);

        Mail::assertSent(ServiceStatusChangedMail::class, function (ServiceStatusChangedMail $mail) use ($service): bool {
            $rendered = $mail->render();

            return $mail->hasTo('ops@example.com')
                && $mail->service->is($service)
                && $mail->currentStatus === Service::STATUS_UP
                && $mail->envelope()->subject === '['.config('app.name').'] It Is Up!: '.$service->name
                && $mail->downtimeDurationSummary === '1 minute'
                && str_contains($rendered, 'Downtime:</strong> 1 minute');
        });
    }

    public function test_monitoring_command_sends_admin_alerts_when_webhook_delivery_fails(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 11:00:00');

        $this->travelTo($checkedAt);

        User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $recipient = Recipient::factory()->webhook()->basicAuth()->create([
            'name' => 'Ops Webhook',
            'endpoint' => 'webhook://hooks.example.com/ops',
            'webhook_auth_username' => 'ops-user',
            'webhook_auth_password' => 'ops-pass',
        ]);

        $service = Service::factory()->currentlyUp()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com/status',
            'next_check_at' => $checkedAt,
        ]);

        $service->recipients()->sync([$recipient->id]);

        Http::fake([
            'https://billing.example.com/status' => Http::response('Server error', 503),
            'https://hooks.example.com/ops' => function ($request) {
                $payload = json_decode($request->body(), true);

                $this->assertSame('Basic '.base64_encode('ops-user:ops-pass'), $request->header('Authorization')[0] ?? null);
                $this->assertSame('Billing API', $payload['service']['name'] ?? null);
                $this->assertSame('down', $payload['status'] ?? null);
                $this->assertArrayNotHasKey('downtime_duration', $payload);
                $this->assertSame('Expected HTTP 200 response but received 503.', $payload['reason'] ?? null);

                return Http::response(['accepted' => false], 500);
            },
        ]);

        $this->artisan('monitor:services')->assertSuccessful();

        $service->refresh();

        $this->assertSame(Service::STATUS_DOWN, $service->current_status);

        Mail::assertSent(WebhookDeliveryFailedMail::class, function (WebhookDeliveryFailedMail $mail) use ($service): bool {
            return $mail->hasTo('admin@example.com')
                && $mail->service->is($service)
                && count($mail->failures) === 1
                && $mail->failures[0]['recipient_name'] === 'Ops Webhook';
        });
    }

    public function test_monitoring_command_includes_downtime_in_recovery_webhook_payloads(): void
    {
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 11:30:00');

        $this->travelTo($checkedAt);

        $recipient = Recipient::factory()->webhook()->create([
            'endpoint' => 'webhook://hooks.example.com/recovery',
        ]);

        $service = Service::factory()->expectsText()->create([
            'name' => 'Marketing Site',
            'url' => 'https://status.example.com',
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
            'current_status' => Service::STATUS_DOWN,
            'last_check_reason' => 'Response body did not contain the expected text.',
            'last_checked_at' => $checkedAt->subMinutes(2),
            'next_check_at' => $checkedAt->subSecond(),
            'last_status_changed_at' => $checkedAt->subMinutes(2),
        ]);

        $service->recipients()->sync([$recipient->id]);

        Http::fake([
            'https://status.example.com' => Http::response('All systems operational', 200),
            'https://hooks.example.com/recovery' => function ($request) {
                $payload = json_decode($request->body(), true);

                $this->assertSame('up', $payload['status'] ?? null);
                $this->assertSame('down', $payload['previous_status'] ?? null);
                $this->assertSame(120, $payload['downtime_duration']['seconds'] ?? null);
                $this->assertSame('2 minutes', $payload['downtime_duration']['human'] ?? null);

                return Http::response(['accepted' => true], 200);
            },
        ]);

        $this->artisan('monitor:services')->assertSuccessful();
    }

    public function test_monitoring_command_sends_ssl_expiry_warning_emails_at_most_once_per_day(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 12:00:00');

        $this->travelTo($checkedAt);

        $recipient = Recipient::factory()->mail()->create([
            'endpoint' => 'mailto://ops@example.com',
        ]);

        $service = Service::factory()->currentlyUp()->create([
            'name' => 'Customer Portal',
            'url' => 'https://portal.example.com/health',
            'ssl_expiry_notifications_enabled' => true,
            'next_check_at' => $checkedAt->subSecond(),
        ]);

        $service->recipients()->sync([$recipient->id]);

        $this->mock(SslCertificateInspector::class, function (MockInterface $mock) use ($checkedAt): void {
            $mock->shouldReceive('inspect')
                ->once()
                ->andReturn(new SslCertificateInspectionResult(
                    expiresAt: $checkedAt->addDays(8),
                ));
        });

        Http::fake([
            'https://portal.example.com/health' => Http::response('Healthy', 200),
        ]);

        $this->artisan('monitor:services')->assertSuccessful();

        $service->refresh();

        $this->assertSame($checkedAt->toDateTimeString(), $service->last_ssl_expiry_notification_sent_at?->toDateTimeString());

        Mail::assertSent(ServiceSslExpiryWarningMail::class, function (ServiceSslExpiryWarningMail $mail) use ($service): bool {
            return $mail->hasTo('ops@example.com')
                && $mail->service->is($service);
        });

        $this->travelTo($checkedAt->addHours(12));

        $service->forceFill([
            'next_check_at' => now()->subSecond(),
        ])->save();

        Http::fake([
            'https://portal.example.com/health' => Http::response('Healthy', 200),
        ]);

        $this->artisan('monitor:services')->assertSuccessful();

        Mail::assertSent(ServiceSslExpiryWarningMail::class, 1);
    }

    public function test_monitoring_command_sends_ssl_expiry_webhook_payloads(): void
    {
        Http::preventStrayRequests();

        $checkedAt = CarbonImmutable::parse('2026-04-04 12:30:00');

        $this->travelTo($checkedAt);

        $recipient = Recipient::factory()->webhook()->create([
            'endpoint' => 'webhook://hooks.example.com/ssl',
        ]);

        $service = Service::factory()->currentlyUp()->create([
            'name' => 'Customer Portal',
            'url' => 'https://portal.example.com/health',
            'ssl_expiry_notifications_enabled' => true,
            'next_check_at' => $checkedAt->subSecond(),
        ]);

        $service->recipients()->sync([$recipient->id]);

        $this->mock(SslCertificateInspector::class, function (MockInterface $mock) use ($checkedAt): void {
            $mock->shouldReceive('inspect')
                ->once()
                ->andReturn(new SslCertificateInspectionResult(
                    expiresAt: $checkedAt->addDays(5),
                ));
        });

        Http::fake([
            'https://portal.example.com/health' => Http::response('Healthy', 200),
            'https://hooks.example.com/ssl' => function ($request) use ($checkedAt) {
                $payload = json_decode($request->body(), true);

                $this->assertSame('service.ssl_expiring', $payload['event'] ?? null);
                $this->assertSame('Customer Portal', $payload['service']['name'] ?? null);
                $this->assertSame($checkedAt->addDays(5)->toIso8601String(), $payload['ssl']['expires_at'] ?? null);
                $this->assertSame(5, $payload['ssl']['days_until_expiry'] ?? null);

                return Http::response(['accepted' => true], 200);
            },
        ]);

        $this->artisan('monitor:services')->assertSuccessful();
    }
}
