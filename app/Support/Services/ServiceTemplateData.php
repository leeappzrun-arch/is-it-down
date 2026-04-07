<?php

namespace App\Support\Services;

use App\Models\Service;
use Illuminate\Support\Arr;

class ServiceTemplateData
{
    /**
     * Build the persistence payload for a service template.
     *
     * @param  array<string, mixed>  $validated
     * @return array{name: string, configuration: array{name: string, interval_seconds: int, expect_type: ?string, expect_value: ?string, service_group_ids: array<int, int>, recipient_group_ids: array<int, int>, recipient_ids: array<int, int>}}
     */
    public static function payload(array $validated): array
    {
        return [
            'name' => trim((string) $validated['templateName']),
            'configuration' => self::configuration($validated),
        ];
    }

    /**
     * Build the persistence payload for a service-derived template.
     *
     * @return array{name: string, configuration: array{name: string, interval_seconds: int, expect_type: ?string, expect_value: ?string, service_group_ids: array<int, int>, recipient_group_ids: array<int, int>, recipient_ids: array<int, int>}}
     */
    public static function payloadFromService(Service $service, string $templateName): array
    {
        $service->loadMissing(['groups:id', 'recipientGroups:id', 'recipients:id']);

        return [
            'name' => trim($templateName),
            'configuration' => self::normalizeConfiguration([
                'name' => $service->name,
                'interval_seconds' => $service->interval_seconds,
                'expect_type' => $service->expect_type,
                'expect_value' => $service->expect_value,
                'service_group_ids' => $service->groups->pluck('id')->all(),
                'recipient_group_ids' => $service->recipientGroups->pluck('id')->all(),
                'recipient_ids' => $service->recipients->pluck('id')->all(),
            ]),
        ];
    }

    /**
     * Normalize a template configuration payload.
     *
     * @return array{name: string, interval_seconds: int, expect_type: ?string, expect_value: ?string, service_group_ids: array<int, int>, recipient_group_ids: array<int, int>, recipient_ids: array<int, int>}
     */
    public static function normalizeConfiguration(mixed $configuration): array
    {
        if (! is_array($configuration)) {
            $configuration = [];
        }

        $expectType = (string) Arr::get($configuration, 'expect_type', Service::EXPECT_NONE);
        $expectValue = trim((string) Arr::get($configuration, 'expect_value', ''));

        if (! array_key_exists($expectType, Service::expectTypes())) {
            $expectType = Service::EXPECT_NONE;
        }

        return [
            'name' => trim((string) Arr::get($configuration, 'name', '')),
            'interval_seconds' => self::normalizeInterval(Arr::get($configuration, 'interval_seconds', Service::INTERVAL_1_MINUTE)),
            'expect_type' => $expectType === Service::EXPECT_NONE ? null : $expectType,
            'expect_value' => $expectType === Service::EXPECT_NONE || $expectValue === '' ? null : $expectValue,
            'service_group_ids' => self::normalizeIdList(Arr::get($configuration, 'service_group_ids', [])),
            'recipient_group_ids' => self::normalizeIdList(Arr::get($configuration, 'recipient_group_ids', [])),
            'recipient_ids' => self::normalizeIdList(Arr::get($configuration, 'recipient_ids', [])),
        ];
    }

    /**
     * Build the service form state from a stored template configuration.
     *
     * @param  array<string, mixed>  $configuration
     * @return array{name: string, url: string, intervalSeconds: int, expectType: string, expectValue: string, selectedServiceGroupIds: array<int, string>, selectedRecipientGroupIds: array<int, string>, selectedRecipientIds: array<int, string>}
     */
    public static function serviceFormState(array $configuration): array
    {
        $configuration = self::normalizeConfiguration($configuration);

        return [
            'name' => $configuration['name'],
            'url' => '',
            'intervalSeconds' => $configuration['interval_seconds'],
            'expectType' => $configuration['expect_type'] ?? Service::EXPECT_NONE,
            'expectValue' => $configuration['expect_value'] ?? '',
            'selectedServiceGroupIds' => array_map(fn (int $id): string => (string) $id, $configuration['service_group_ids']),
            'selectedRecipientGroupIds' => array_map(fn (int $id): string => (string) $id, $configuration['recipient_group_ids']),
            'selectedRecipientIds' => array_map(fn (int $id): string => (string) $id, $configuration['recipient_ids']),
        ];
    }

    /**
     * Build the normalized configuration from validated template form data.
     *
     * @param  array<string, mixed>  $validated
     * @return array{name: string, interval_seconds: int, expect_type: ?string, expect_value: ?string, service_group_ids: array<int, int>, recipient_group_ids: array<int, int>, recipient_ids: array<int, int>}
     */
    private static function configuration(array $validated): array
    {
        return self::normalizeConfiguration([
            'name' => $validated['serviceName'] ?? '',
            'interval_seconds' => $validated['intervalSeconds'] ?? Service::INTERVAL_1_MINUTE,
            'expect_type' => $validated['expectType'] ?? Service::EXPECT_NONE,
            'expect_value' => $validated['expectValue'] ?? '',
            'service_group_ids' => $validated['selectedServiceGroupIds'] ?? [],
            'recipient_group_ids' => $validated['selectedRecipientGroupIds'] ?? [],
            'recipient_ids' => $validated['selectedRecipientIds'] ?? [],
        ]);
    }

    /**
     * Normalize the configured interval to a supported option.
     */
    private static function normalizeInterval(mixed $value): int
    {
        $interval = (int) $value;

        return array_key_exists($interval, Service::intervalOptions())
            ? $interval
            : Service::INTERVAL_1_MINUTE;
    }

    /**
     * Normalize a configured id list to a unique set of integers.
     *
     * @return array<int, int>
     */
    private static function normalizeIdList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_unique(array_map(fn (mixed $item): int => (int) $item, $value)),
            fn (int $item): bool => $item > 0,
        ));
    }
}
