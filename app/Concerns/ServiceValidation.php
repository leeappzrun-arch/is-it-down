<?php

namespace App\Concerns;

use App\Models\Service;
use Illuminate\Validation\Rule;

trait ServiceValidation
{
    /**
     * Get the supported interval options.
     *
     * @return array<int, string>
     */
    protected function serviceIntervalOptions(): array
    {
        return Service::intervalOptions();
    }

    /**
     * Get the supported expectation types.
     *
     * @return array<string, string>
     */
    protected function serviceExpectationOptions(): array
    {
        return Service::expectTypes();
    }

    /**
     * Get the validation rules for service upserts.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function serviceValidationRules(string $expectType, bool $requiresName = true, bool $requiresUrl = true): array
    {
        return [
            'name' => [Rule::requiredIf($requiresName), 'nullable', 'string', 'max:255'],
            'url' => [
                Rule::requiredIf($requiresUrl),
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) use ($requiresUrl): void {
                    if (blank($value)) {
                        if ($requiresUrl) {
                            $fail(__('Service URLs must use the format example.com/status or https://example.com/status.'));
                        }

                        return;
                    }

                    if (! is_string($value)) {
                        $fail(__('The URL must be a string.'));

                        return;
                    }

                    $normalizedUrl = trim($value);

                    if ($normalizedUrl !== '' && ! str_starts_with($normalizedUrl, 'http://') && ! str_starts_with($normalizedUrl, 'https://')) {
                        $normalizedUrl = 'https://'.ltrim($normalizedUrl, '/');
                    }

                    if (! filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
                        $fail(__('Service URLs must use the format example.com/status or https://example.com/status.'));
                    }
                },
            ],
            'intervalSeconds' => ['required', 'integer', Rule::in(array_keys($this->serviceIntervalOptions()))],
            'expectType' => ['required', Rule::in(array_keys($this->serviceExpectationOptions()))],
            'expectValue' => [
                Rule::requiredIf(fn (): bool => $expectType !== Service::EXPECT_NONE),
                'nullable',
                'string',
                'max:65535',
                function (string $attribute, mixed $value, \Closure $fail) use ($expectType): void {
                    if ($expectType !== Service::EXPECT_REGEX || blank($value)) {
                        return;
                    }

                    if (! is_string($value) || @preg_match($value, '') === false) {
                        $fail(__('Regular expressions must be valid PHP patterns including delimiters, for example /healthy/i.'));
                    }
                },
            ],
            'additionalHeaders' => ['array'],
            'additionalHeaders.*.name' => ['required', 'string', 'max:255'],
            'additionalHeaders.*.value' => ['required', 'string', 'max:65535'],
            'sslExpiryNotificationsEnabled' => ['boolean'],
            'selectedServiceGroupIds' => ['array'],
            'selectedServiceGroupIds.*' => ['integer', Rule::exists('service_groups', 'id')],
            'selectedRecipientGroupIds' => ['array'],
            'selectedRecipientGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'selectedRecipientIds' => ['array'],
            'selectedRecipientIds.*' => ['integer', Rule::exists('recipients', 'id')],
        ];
    }

    /**
     * Get the validation rules for service template upserts.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function serviceTemplateValidationRules(string $expectType, ?int $serviceTemplateId = null): array
    {
        return [
            'templateName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_templates', 'name')->ignore($serviceTemplateId),
            ],
            'serviceName' => ['required', 'string', 'max:255'],
            'intervalSeconds' => ['required', 'integer', Rule::in(array_keys($this->serviceIntervalOptions()))],
            'expectType' => ['required', Rule::in(array_keys($this->serviceExpectationOptions()))],
            'expectValue' => [
                Rule::requiredIf(fn (): bool => $expectType !== Service::EXPECT_NONE),
                'nullable',
                'string',
                'max:65535',
                function (string $attribute, mixed $value, \Closure $fail) use ($expectType): void {
                    if ($expectType !== Service::EXPECT_REGEX || blank($value)) {
                        return;
                    }

                    if (! is_string($value) || @preg_match($value, '') === false) {
                        $fail(__('Regular expressions must be valid PHP patterns including delimiters, for example /healthy/i.'));
                    }
                },
            ],
            'additionalHeaders' => ['array'],
            'additionalHeaders.*.name' => ['required', 'string', 'max:255'],
            'additionalHeaders.*.value' => ['required', 'string', 'max:65535'],
            'sslExpiryNotificationsEnabled' => ['boolean'],
            'selectedServiceGroupIds' => ['array'],
            'selectedServiceGroupIds.*' => ['integer', Rule::exists('service_groups', 'id')],
            'selectedRecipientGroupIds' => ['array'],
            'selectedRecipientGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'selectedRecipientIds' => ['array'],
            'selectedRecipientIds.*' => ['integer', Rule::exists('recipients', 'id')],
        ];
    }

    /**
     * Get the validation rules for service group upserts.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function serviceGroupValidationRules(?int $serviceGroupId = null): array
    {
        return [
            'groupName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_groups', 'name')->ignore($serviceGroupId),
            ],
            'groupSelectedRecipientGroupIds' => ['array'],
            'groupSelectedRecipientGroupIds.*' => ['integer', Rule::exists('recipient_groups', 'id')],
            'groupSelectedRecipientIds' => ['array'],
            'groupSelectedRecipientIds.*' => ['integer', Rule::exists('recipients', 'id')],
        ];
    }
}
