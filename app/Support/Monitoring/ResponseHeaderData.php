<?php

namespace App\Support\Monitoring;

final class ResponseHeaderData
{
    /**
     * Normalize response headers for storage and display.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public static function normalize(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $values = is_array($value)
                ? array_values(array_filter(array_map(
                    static fn (mixed $headerValue): string => trim((string) $headerValue),
                    $value,
                )))
                : [trim((string) $value)];

            if ($values === []) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'value' => implode(', ', $values),
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $normalized;
    }
}
