<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceDowntime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceDowntime>
 */
class ServiceDowntimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(5, 180));

        return [
            'service_id' => Service::factory(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes(fake()->numberBetween(1, 30)),
            'started_reason' => 'Expected HTTP 200 response but received 503.',
            'latest_reason' => 'Expected HTTP 200 response but received 503.',
            'recovery_reason' => 'Received an HTTP 200 response.',
            'started_response_code' => 503,
            'started_response_headers' => null,
            'latest_response_code' => 503,
            'latest_response_headers' => null,
            'recovery_response_code' => 200,
            'last_checked_at' => $startedAt->copy()->addMinutes(1),
            'last_check_attempts' => 2,
            'screenshot_disk' => null,
            'screenshot_path' => null,
            'screenshot_captured_at' => null,
            'ai_summary' => null,
            'ai_summary_created_at' => null,
        ];
    }

    /**
     * Indicate that the downtime is still ongoing.
     */
    public function ongoing(): static
    {
        return $this->state(fn (): array => [
            'ended_at' => null,
            'recovery_reason' => null,
            'recovery_response_code' => null,
        ]);
    }
}
