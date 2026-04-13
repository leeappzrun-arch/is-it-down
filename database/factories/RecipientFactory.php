<?php

namespace Database\Factories;

use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Alerts',
            'endpoint' => 'mailto://'.fake()->unique()->safeEmail(),
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_NONE,
            'webhook_auth_username' => null,
            'webhook_auth_password' => null,
            'webhook_auth_token' => null,
            'webhook_auth_header_name' => null,
            'webhook_auth_header_value' => null,
            'additional_headers' => [],
        ];
    }

    /**
     * Indicate that the recipient uses a mail endpoint.
     */
    public function mail(): static
    {
        return $this->state(fn (array $attributes) => [
            'endpoint' => 'mailto://'.fake()->unique()->safeEmail(),
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_NONE,
            'webhook_auth_username' => null,
            'webhook_auth_password' => null,
            'webhook_auth_token' => null,
            'webhook_auth_header_name' => null,
            'webhook_auth_header_value' => null,
            'additional_headers' => [],
        ]);
    }

    /**
     * Indicate that the recipient uses a webhook endpoint.
     */
    public function webhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'endpoint' => 'webhook://'.fake()->domainName().'/hooks/'.fake()->uuid(),
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_NONE,
            'additional_headers' => [],
        ]);
    }

    /**
     * Indicate that the recipient uses bearer token authentication.
     */
    public function bearerToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_BEARER,
            'webhook_auth_token' => fake()->sha256(),
        ]);
    }

    /**
     * Indicate that the recipient uses basic authentication.
     */
    public function basicAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_BASIC,
            'webhook_auth_username' => fake()->userName(),
            'webhook_auth_password' => fake()->password(16, 24),
            'webhook_auth_token' => null,
            'webhook_auth_header_name' => null,
            'webhook_auth_header_value' => null,
        ]);
    }

    /**
     * Indicate that the recipient uses a custom header for authentication.
     */
    public function customHeader(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_HEADER,
            'webhook_auth_username' => null,
            'webhook_auth_password' => null,
            'webhook_auth_token' => null,
            'webhook_auth_header_name' => 'X-Webhook-Key',
            'webhook_auth_header_value' => fake()->sha256(),
        ]);
    }
}
