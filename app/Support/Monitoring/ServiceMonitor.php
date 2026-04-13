<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ServiceMonitor
{
    public function __construct(
        private readonly ServiceMonitorPause $pause,
    ) {}

    /**
     * Check whether the given service is currently reachable and healthy.
     */
    public function check(Service $service): ServiceCheckResult
    {
        $firstAttempt = $this->performCheck($service);

        if ($firstAttempt->status !== Service::STATUS_DOWN) {
            return $firstAttempt;
        }

        $this->pause->pause();

        $secondAttempt = $this->performCheck($service);
        $delaySeconds = max(0, (int) config('services.monitoring.failure_retry_delay_seconds', 3));
        $retrySummary = $delaySeconds > 0
            ? ' after retrying '.$delaySeconds.' seconds later'
            : ' after retrying immediately';

        if ($secondAttempt->status === Service::STATUS_UP) {
            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'The first check failed, but the service recovered'.$retrySummary.'. '.$secondAttempt->reason,
                responseCode: $secondAttempt->responseCode,
                bodyExcerpt: $secondAttempt->bodyExcerpt,
                connectionSucceeded: $secondAttempt->connectionSucceeded,
                attemptCount: 2,
            );
        }

        return new ServiceCheckResult(
            status: Service::STATUS_DOWN,
            reason: 'The service still appeared down'.$retrySummary.'. '.$secondAttempt->reason,
            responseCode: $secondAttempt->responseCode,
            bodyExcerpt: $secondAttempt->bodyExcerpt,
            connectionSucceeded: $secondAttempt->connectionSucceeded,
            attemptCount: 2,
        );
    }

    /**
     * Perform a single service-check attempt.
     */
    private function performCheck(Service $service): ServiceCheckResult
    {
        try {
            $request = Http::accept('*/*')
                ->connectTimeout(5)
                ->timeout(10);

            if ($service->hasAdditionalHeaders()) {
                $request = $request->withHeaders($service->requestHeaders());
            }

            $response = $request
                ->get($service->url);
        } catch (Throwable $throwable) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: 'Request failed: '.trim($throwable->getMessage()),
            );
        }

        $body = $response->body();
        $bodyExcerpt = Str::limit(trim(strip_tags($body)), 500);

        if ($response->status() !== 200) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: 'Expected HTTP 200 response but received '.$response->status().'.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
            );
        }

        if (! $service->hasExpectation()) {
            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
            );
        }

        if ($service->expect_type === Service::EXPECT_TEXT) {
            if (! Str::contains($body, (string) $service->expect_value)) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not contain the expected text.',
                    responseCode: $response->status(),
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected text was present.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
            );
        }

        if ($service->expect_type === Service::EXPECT_REGEX) {
            $matched = @preg_match((string) $service->expect_value, $body);

            if ($matched === false) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'The configured regular expression expectation is invalid.',
                    responseCode: $response->status(),
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                );
            }

            if ($matched !== 1) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not match the expected regular expression.',
                    responseCode: $response->status(),
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected regular expression matched.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
            );
        }

        return new ServiceCheckResult(
            status: Service::STATUS_UP,
            reason: 'Received an HTTP 200 response.',
            responseCode: $response->status(),
            bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
            connectionSucceeded: true,
        );
    }
}
