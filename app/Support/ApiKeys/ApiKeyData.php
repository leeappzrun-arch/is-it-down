<?php

namespace App\Support\ApiKeys;

use App\Models\ApiKey;
use App\Models\User;
use App\Support\ApiKeyPermissions;
use Carbon\CarbonInterface;

class ApiKeyData
{
    /**
     * Build the persistence payload for a new API key.
     *
     * @param  array{name: string, expirationOption: string, selectedPermissions: array<int, string>}  $validated
     * @return array<string, mixed>
     */
    public static function createPayload(array $validated, User $creator, string $plainTextToken): array
    {
        return [
            'name' => trim($validated['name']),
            'user_id' => $creator->id,
            'created_by_id' => $creator->id,
            'token_prefix' => substr($plainTextToken, 0, 12),
            'token_hash' => ApiKey::hashToken($plainTextToken),
            'permissions' => ApiKeyPermissions::normalize($validated['selectedPermissions']),
            'expires_at' => self::resolveExpirationDate($validated['expirationOption']),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Resolve an expiration preset to a concrete timestamp.
     */
    public static function resolveExpirationDate(string $expirationOption): ?CarbonInterface
    {
        return match ($expirationOption) {
            '6_months' => now()->addMonthsNoOverflow(6),
            '1_year' => now()->addYear(),
            '2_years' => now()->addYears(2),
            default => null,
        };
    }
}
