<?php

namespace App\Support\Services;

use App\Models\Service;
use Illuminate\Support\Str;

class ServiceData
{
    /**
     * Normalize the configured additional headers.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public static function normalizeAdditionalHeaders(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $headers = [];

        foreach ($value as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = trim((string) ($header['name'] ?? ''));
            $headerValue = trim((string) ($header['value'] ?? ''));

            if ($name === '' && $headerValue === '') {
                continue;
            }

            $headers[] = [
                'name' => $name,
                'value' => $headerValue,
            ];
        }

        return $headers;
    }

    /**
     * Build keyed request headers for the HTTP client.
     *
     * @return array<string, string>
     */
    public static function requestHeaders(mixed $value): array
    {
        return collect(self::normalizeAdditionalHeaders($value))
            ->mapWithKeys(fn (array $header): array => [$header['name'] => $header['value']])
            ->all();
    }

    /**
     * Normalize the submitted URL.
     */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://'.ltrim($url, '/');
    }

    /**
     * Build the persistence payload for a service.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function payload(array $validated): array
    {
        $expectType = (string) $validated['expectType'];
        $expectValue = trim((string) ($validated['expectValue'] ?? ''));
        $sslExpiryNotificationsEnabled = (bool) ($validated['sslExpiryNotificationsEnabled'] ?? false);

        $payload = [
            'name' => trim((string) $validated['name']),
            'url' => self::normalizeUrl((string) $validated['url']),
            'interval_seconds' => (int) $validated['intervalSeconds'],
            'expect_type' => $expectType === Service::EXPECT_NONE ? null : $expectType,
            'expect_value' => $expectType === Service::EXPECT_NONE || $expectValue === '' ? null : $expectValue,
            'additional_headers' => self::normalizeAdditionalHeaders($validated['additionalHeaders'] ?? []),
            'ssl_expiry_notifications_enabled' => $sslExpiryNotificationsEnabled,
        ];

        if (! $sslExpiryNotificationsEnabled) {
            $payload['last_ssl_expiry_notification_sent_at'] = null;
        }

        return $payload;
    }
}
