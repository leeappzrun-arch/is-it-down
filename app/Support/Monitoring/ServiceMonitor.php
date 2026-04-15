<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ServiceMonitor
{
    public function __construct(
        private readonly BrowserPageMonitor $browserPageMonitor,
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

        return new ServiceCheckResult(
            status: $secondAttempt->status,
            reason: $secondAttempt->reason,
            responseCode: $secondAttempt->responseCode,
            bodyExcerpt: $secondAttempt->bodyExcerpt,
            connectionSucceeded: $secondAttempt->connectionSucceeded,
            attemptCount: 2,
            responseHeaders: $secondAttempt->responseHeaders,
        );
    }

    /**
     * Perform a single service-check attempt.
     */
    private function performCheck(Service $service): ServiceCheckResult
    {
        if ($service->usesBrowserMonitoring()) {
            return $this->performBrowserCheck($service);
        }

        return $this->performHttpCheck($service);
    }

    /**
     * Perform a single HTTP service-check attempt.
     */
    private function performHttpCheck(Service $service): ServiceCheckResult
    {
        try {
            $request = Http::withHeaders($this->defaultRequestHeaders())
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
        $responseHeaders = ResponseHeaderData::normalize($response->headers());

        if ($response->status() !== 200) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: $this->failureReason($response, $responseHeaders, $body),
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        if (! $service->hasExpectation()) {
            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
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
                    responseHeaders: $responseHeaders,
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected text was present.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
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
                    responseHeaders: $responseHeaders,
                );
            }

            if ($matched !== 1) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not match the expected regular expression.',
                    responseCode: $response->status(),
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                    responseHeaders: $responseHeaders,
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected regular expression matched.',
                responseCode: $response->status(),
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        return new ServiceCheckResult(
            status: Service::STATUS_UP,
            reason: 'Received an HTTP 200 response.',
            responseCode: $response->status(),
            bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
            connectionSucceeded: true,
            responseHeaders: $responseHeaders,
        );
    }

    /**
     * Perform a single browser-backed service-check attempt.
     */
    private function performBrowserCheck(Service $service): ServiceCheckResult
    {
        try {
            $page = $this->browserPageMonitor->fetch($service);
        } catch (Throwable $throwable) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: 'Browser monitoring failed: '.trim($throwable->getMessage()),
            );
        }

        $responseCode = $page['status'];
        $body = $page['body'];
        $bodyExcerpt = Str::limit(trim(strip_tags($body)), 500);
        $responseHeaders = $page['headers'];

        if ($responseCode === null) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: 'Browser monitoring did not report an HTTP response status.',
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        if ($responseCode !== 200) {
            return new ServiceCheckResult(
                status: Service::STATUS_DOWN,
                reason: $this->failureReason($responseCode, $responseHeaders, $body),
                responseCode: $responseCode,
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        if (! $service->hasExpectation()) {
            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response.',
                responseCode: $responseCode,
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        if ($service->expect_type === Service::EXPECT_TEXT) {
            if (! Str::contains($body, (string) $service->expect_value)) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not contain the expected text.',
                    responseCode: $responseCode,
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                    responseHeaders: $responseHeaders,
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected text was present.',
                responseCode: $responseCode,
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        if ($service->expect_type === Service::EXPECT_REGEX) {
            $matched = @preg_match((string) $service->expect_value, $body);

            if ($matched === false) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'The configured regular expression expectation is invalid.',
                    responseCode: $responseCode,
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                    responseHeaders: $responseHeaders,
                );
            }

            if ($matched !== 1) {
                return new ServiceCheckResult(
                    status: Service::STATUS_DOWN,
                    reason: 'Response body did not match the expected regular expression.',
                    responseCode: $responseCode,
                    bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                    connectionSucceeded: true,
                    responseHeaders: $responseHeaders,
                );
            }

            return new ServiceCheckResult(
                status: Service::STATUS_UP,
                reason: 'Received an HTTP 200 response and the expected regular expression matched.',
                responseCode: $responseCode,
                bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
                connectionSucceeded: true,
                responseHeaders: $responseHeaders,
            );
        }

        return new ServiceCheckResult(
            status: Service::STATUS_UP,
            reason: 'Received an HTTP 200 response.',
            responseCode: $responseCode,
            bodyExcerpt: $bodyExcerpt !== '' ? $bodyExcerpt : null,
            connectionSucceeded: true,
            responseHeaders: $responseHeaders,
        );
    }

    /**
     * Resolve the default headers used for all monitoring requests.
     *
     * @return array<string, string>
     */
    private function defaultRequestHeaders(): array
    {
        return collect(config('services.monitoring.default_request_headers', []))
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->all();
    }

    /**
     * Build the down reason for a non-200 response.
     *
     * @param  array<int, array{name: string, value: string}>  $responseHeaders
     */
    private function failureReason(Response|int $response, array $responseHeaders, string $body): string
    {
        $status = $response instanceof Response ? $response->status() : $response;

        if ($this->looksLikeCloudflareProtection($status, $responseHeaders, $body)) {
            return match ($status) {
                429 => 'Cloudflare rate limited the monitor request with HTTP 429 Too Many Requests. This often indicates temporary protection rather than a real outage.',
                403 => 'Cloudflare blocked or challenged the monitor request with HTTP 403 Forbidden. This often indicates bot protection rather than a real outage.',
                default => 'Cloudflare intercepted the monitor request with HTTP '.$this->statusSummary($status).'. This may indicate temporary protection or rate limiting rather than a real outage.',
            };
        }

        if ($this->looksRateLimited($status, $responseHeaders, $body)) {
            return match ($status) {
                429 => 'The service rate limited the monitor request with HTTP 429 Too Many Requests.',
                default => 'The service appears to have rate limited the monitor request with HTTP '.$this->statusSummary($status).'.',
            };
        }

        return 'Expected HTTP 200 response but received '.$status.'.';
    }

    /**
     * Determine whether the failed response looks like Cloudflare protection.
     *
     * @param  array<int, array{name: string, value: string}>  $responseHeaders
     */
    private function looksLikeCloudflareProtection(int $status, array $responseHeaders, string $body): bool
    {
        $headerMap = $this->headerMap($responseHeaders);
        $bodyLower = Str::lower($body);
        $hasChallengeMarkers = Str::contains($bodyLower, [
            'attention required! | cloudflare',
            'cloudflare ray id',
            '/cdn-cgi/challenge-platform',
            'cf-browser-verification',
            'error code 1020',
        ]);
        $isCloudflareEdge = ($headerMap['server'] ?? null) === 'cloudflare'
            || array_key_exists('cf-ray', $headerMap)
            || array_key_exists('cf-cache-status', $headerMap);

        if ($hasChallengeMarkers) {
            return true;
        }

        if (! $isCloudflareEdge) {
            return false;
        }

        return in_array($status, [403, 429], true);
    }

    /**
     * Determine whether the failed response looks like rate limiting.
     *
     * @param  array<int, array{name: string, value: string}>  $responseHeaders
     */
    private function looksRateLimited(int $status, array $responseHeaders, string $body): bool
    {
        if ($status === 429) {
            return true;
        }

        $headerMap = $this->headerMap($responseHeaders);

        if (
            array_key_exists('retry-after', $headerMap)
            || ($headerMap['x-ratelimit-remaining'] ?? null) === '0'
        ) {
            return true;
        }

        return Str::contains(Str::lower($body), [
            'too many requests',
            'rate limit',
            'rate limited',
        ]);
    }

    /**
     * Build a lower-cased header map for lookup helpers.
     *
     * @param  array<int, array{name: string, value: string}>  $responseHeaders
     * @return array<string, string>
     */
    private function headerMap(array $responseHeaders): array
    {
        return collect($responseHeaders)
            ->filter(fn (mixed $header): bool => is_array($header))
            ->reduce(function (array $map, array $header): array {
                $name = Str::lower((string) ($header['name'] ?? ''));
                $value = trim((string) ($header['value'] ?? ''));

                if ($name !== '' && $value !== '') {
                    $map[$name] = $value;
                }

                return $map;
            }, []);
    }

    /**
     * Format an HTTP status code for user-facing reasons.
     */
    private function statusSummary(int $status): string
    {
        return match ($status) {
            403 => '403 Forbidden',
            429 => '429 Too Many Requests',
            503 => '503 Service Unavailable',
            default => (string) $status,
        };
    }
}
