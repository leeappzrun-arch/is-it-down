<?php

namespace App\Concerns;

use App\Models\Recipient;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait RecipientValidation
{
    /**
     * Get the supported recipient endpoint types.
     *
     * @return array<string, string>
     */
    protected function recipientEndpointTypeOptions(): array
    {
        return [
            Recipient::TYPE_MAIL => 'Email',
            Recipient::TYPE_WEBHOOK => 'Webhook',
        ];
    }

    /**
     * Get the supported webhook authentication types.
     *
     * @return array<string, string>
     */
    protected function recipientWebhookAuthenticationOptions(): array
    {
        return [
            Recipient::WEBHOOK_AUTH_NONE => 'None',
            Recipient::WEBHOOK_AUTH_BEARER => 'Bearer token',
            Recipient::WEBHOOK_AUTH_BASIC => 'Basic auth',
            Recipient::WEBHOOK_AUTH_HEADER => 'Custom header',
        ];
    }

    /**
     * Get the validation rules for recipient upserts.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function recipientValidationRules(string $endpointType, string $webhookAuthType): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'endpointType' => ['required', 'string', Rule::in(array_keys($this->recipientEndpointTypeOptions()))],
            'endpointTarget' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) use ($endpointType): void {
                    if (! is_string($value)) {
                        $fail(__('The destination must be a string.'));

                        return;
                    }

                    $target = trim($value);

                    if ($endpointType === Recipient::TYPE_MAIL) {
                        $target = trim(Str::after($target, 'mailto://'));

                        if ($target === '' || ! filter_var($target, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('Email destinations must use the format name@example.com.'));
                        }

                        return;
                    }

                    if ($endpointType === Recipient::TYPE_WEBHOOK) {
                        $target = trim(Str::after($target, 'webhook://'));
                        $normalizedTarget = Str::startsWith($target, ['http://', 'https://'])
                            ? $target
                            : 'https://'.ltrim($target, '/');

                        if ($target === '' || ! filter_var($normalizedTarget, FILTER_VALIDATE_URL)) {
                            $fail(__('Webhook destinations must use the format example.com/path or https://example.com/path.'));
                        }

                        return;
                    }

                    $fail(__('Choose whether this destination is an email address or a webhook.'));
                },
            ],
            'selectedGroupIds' => ['array'],
            'selectedGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'webhookAuthType' => [
                Rule::requiredIf(fn (): bool => $endpointType === Recipient::TYPE_WEBHOOK),
                Rule::in(Recipient::webhookAuthTypes()),
            ],
            'webhookAuthUsername' => [
                Rule::requiredIf(fn (): bool => $endpointType === Recipient::TYPE_WEBHOOK && $webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC),
                'nullable',
                'string',
                'max:255',
            ],
            'webhookAuthPassword' => [
                Rule::requiredIf(fn (): bool => $endpointType === Recipient::TYPE_WEBHOOK && $webhookAuthType === Recipient::WEBHOOK_AUTH_BASIC),
                'nullable',
                'string',
                'max:255',
            ],
            'webhookAuthToken' => [
                Rule::requiredIf(fn (): bool => $endpointType === Recipient::TYPE_WEBHOOK && $webhookAuthType === Recipient::WEBHOOK_AUTH_BEARER),
                'nullable',
                'string',
                'max:2048',
            ],
            'webhookAuthHeaderName' => [
                Rule::requiredIf(fn (): bool => $endpointType === Recipient::TYPE_WEBHOOK && $webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER),
                'nullable',
                'string',
                'max:255',
            ],
            'webhookAuthHeaderValue' => [
                Rule::requiredIf(fn (): bool => $endpointType === Recipient::TYPE_WEBHOOK && $webhookAuthType === Recipient::WEBHOOK_AUTH_HEADER),
                'nullable',
                'string',
                'max:2048',
            ],
        ];
    }
}
