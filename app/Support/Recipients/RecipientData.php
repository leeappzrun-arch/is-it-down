<?php

namespace App\Support\Recipients;

use App\Models\Recipient;
use Illuminate\Support\Str;

class RecipientData
{
    /**
     * Build the model payload from validated input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function payload(array $validated): array
    {
        $endpointType = (string) $validated['endpointType'];
        $endpointTarget = self::normalizeEndpointTarget((string) $validated['endpointTarget'], $endpointType);
        $isWebhookEndpoint = $endpointType === Recipient::TYPE_WEBHOOK;
        $webhookAuthType = $isWebhookEndpoint
            ? (string) $validated['webhookAuthType']
            : Recipient::WEBHOOK_AUTH_NONE;

        return [
            'name' => trim((string) $validated['name']),
            'endpoint' => self::buildEndpoint($endpointType, $endpointTarget),
            'webhook_auth_type' => $webhookAuthType,
            'webhook_auth_username' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC
                ? trim((string) ($validated['webhookAuthUsername'] ?? ''))
                : null,
            'webhook_auth_password' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC
                ? (string) ($validated['webhookAuthPassword'] ?? '')
                : null,
            'webhook_auth_token' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_BEARER
                ? (string) ($validated['webhookAuthToken'] ?? '')
                : null,
            'webhook_auth_header_name' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER
                ? trim((string) ($validated['webhookAuthHeaderName'] ?? ''))
                : null,
            'webhook_auth_header_value' => $isWebhookEndpoint && $webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER
                ? (string) ($validated['webhookAuthHeaderValue'] ?? '')
                : null,
        ];
    }

    /**
     * Normalize a user-provided endpoint target.
     */
    public static function normalizeEndpointTarget(string $target, string $endpointType): string
    {
        $normalizedTarget = trim($target);

        if ($endpointType === Recipient::TYPE_MAIL) {
            return trim(Str::after($normalizedTarget, 'mailto://'));
        }

        return trim(Str::after($normalizedTarget, 'webhook://'));
    }

    /**
     * Build the stored endpoint value.
     */
    public static function buildEndpoint(string $endpointType, string $endpointTarget): string
    {
        return match ($endpointType) {
            Recipient::TYPE_MAIL => 'mailto://'.$endpointTarget,
            Recipient::TYPE_WEBHOOK => 'webhook://'.$endpointTarget,
        };
    }

    /**
     * Parse a stored endpoint into editable form fields.
     *
     * @return array{type: string, target: string}
     */
    public static function parseEndpoint(string $endpoint): array
    {
        if (Str::startsWith($endpoint, 'mailto://')) {
            return [
                'type' => Recipient::TYPE_MAIL,
                'target' => trim(Str::after($endpoint, 'mailto://')),
            ];
        }

        if (Str::startsWith($endpoint, 'webhook://')) {
            return [
                'type' => Recipient::TYPE_WEBHOOK,
                'target' => trim(Str::after($endpoint, 'webhook://')),
            ];
        }

        return [
            'type' => Recipient::TYPE_MAIL,
            'target' => trim($endpoint),
        ];
    }
}
