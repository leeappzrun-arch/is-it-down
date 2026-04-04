<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ServiceMonitor
{
    /**
     * Check whether the given service is currently reachable and healthy.
     */
    public function check(Service $service): ServiceCheckResult
    {
        try {
            $response = Http::accept('*/*')
                ->connectTimeout(5)
                ->timeout(10)
                ->get($service->url);
        } catch (Throwable $throwable) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: 'Request failed: '.trim($throwable->getMessage()),
            );
        }

        if ($response->status() !== 200) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: 'Expected HTTP 200 response but received '.$response->status().'.',
                responseCode: $response->status(),
            );
        }

        if (! $service->hasExpectation()) {
            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response.',
                responseCode: $response->status(),
            );
        }

        $body = $response->body();

        if ($service->expect_type === Service::EXPECT_TEXT) {
            if (! Str::contains($body, (string) $service->expect_value)) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not contain the expected text.',
                    responseCode: $response->status(),
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected text was present.',
                responseCode: $response->status(),
            );
        }

        if ($service->expect_type === Service::EXPECT_REGEX) {
            $matched = @preg_match((string) $service->expect_value, $body);

            if ($matched === false) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'The configured regular expression expectation is invalid.',
                    responseCode: $response->status(),
                );
            }

            if ($matched !== 1) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not match the expected regular expression.',
                    responseCode: $response->status(),
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected regular expression matched.',
                responseCode: $response->status(),
            );
        }

        return new ServiceCheckResult(
            status: Service::STATUS_UP,
            reason: 'Received an HTTP 200 response.',
            responseCode: $response->status(),
        );
    }
}
