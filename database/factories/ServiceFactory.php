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
            'expect_type' => null,
            'expect_value' => null,
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
}
