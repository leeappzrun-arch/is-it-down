<?php

namespace App\Support\Services;

use App\Models\Service;
use Illuminate\Support\Str;

class ServiceData
{
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

        return [
            'name' => trim((string) $validated['name']),
            'url' => self::normalizeUrl((string) $validated['url']),
            'interval_seconds' => (int) $validated['intervalSeconds'],
            'expect_type' => $expectType === Service::EXPECT_NONE ? null : $expectType,
            'expect_value' => $expectType === Service::EXPECT_NONE || $expectValue === '' ? null : $expectValue,
        ];
    }
}
