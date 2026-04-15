<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Status',
            'url' => fake()->url(),
            'interval_seconds' => fake()->randomElement(array_keys(Service::intervalOptions())),
            'monitoring_method' => Service::MONITOR_HTTP,
            'expect_type' => null,
            'expect_value' => null,
            'additional_headers' => [],
            'ssl_expiry_notifications_enabled' => false,
            'current_status' => null,
            'last_response_code' => null,
            'last_response_headers' => null,
            'last_check_reason' => null,
            'last_checked_at' => null,
            'next_check_at' => null,
            'last_status_changed_at' => null,
            'last_ssl_expiry_notification_sent_at' => null,
            'last_screenshot_disk' => null,
            'last_screenshot_path' => null,
            'last_screenshot_captured_at' => null,
        ];
    }

    /**
     * Indicate that the service expects plain text in the response.
     */
    public function expectsText(): static
    {
        return $this->state(fn (): array => [
            'expect_type' => Service::EXPECT_TEXT,
            'expect_value' => 'All systems operational',
        ]);
    }

    /**
     * Indicate that the service expects a regular expression in the response.
     */
    public function expectsRegex(): static
    {
        return $this->state(fn (): array => [
            'expect_type' => Service::EXPECT_REGEX,
            'expect_value' => '/healthy/i',
        ]);
    }

    /**
     * Indicate that the service is currently up.
     */
    public function currentlyUp(): static
    {
        return $this->state(fn (): array => [
            'current_status' => Service::STATUS_UP,
            'last_response_code' => 200,
            'last_check_reason' => 'Received an HTTP 200 response.',
            'last_checked_at' => now()->subMinute(),
            'next_check_at' => now()->addMinute(),
            'last_status_changed_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the service is currently down.
     */
    public function currentlyDown(): static
    {
        return $this->state(fn (): array => [
            'current_status' => Service::STATUS_DOWN,
            'last_response_code' => 503,
            'last_check_reason' => 'Expected HTTP 200 response but received 503.',
            'last_checked_at' => now()->subMinute(),
            'next_check_at' => now()->addMinute(),
            'last_status_changed_at' => now()->subMinutes(10),
        ]);
    }
}
