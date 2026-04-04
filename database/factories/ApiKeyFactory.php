<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainTextToken = ApiKey::generatePlainTextToken();

        return [
            'name' => fake()->words(2, true),
            'user_id' => User::factory(),
            'created_by_id' => User::factory(),
            'token_prefix' => substr($plainTextToken, 0, 12),
            'token_hash' => ApiKey::hashToken($plainTextToken),
            'permissions' => ['recipients:read'],
            'expires_at' => now()->addYear(),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that the API key does not expire.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the API key has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }
}
