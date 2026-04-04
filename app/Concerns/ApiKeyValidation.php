<?php

namespace App\Concerns;

use App\Support\ApiKeyPermissions;
use Illuminate\Validation\Rule;

trait ApiKeyValidation
{
    /**
     * Get the expiration presets available to users.
     *
     * @return array<string, string>
     */
    protected function apiKeyExpirationOptions(): array
    {
        return [
            '6_months' => '6 Months',
            '1_year' => '1 Year',
            '2_years' => '2 Years',
            'never' => 'Never',
        ];
    }

    /**
     * Get the validation rules for API key creation.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function apiKeyCreationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expirationOption' => ['required', Rule::in(array_keys($this->apiKeyExpirationOptions()))],
            'selectedPermissions' => ['required', 'array', 'min:1'],
            'selectedPermissions.*' => ['string', Rule::in(ApiKeyPermissions::all())],
        ];
    }
}
