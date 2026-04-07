<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceTemplate>
 */
class ServiceTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'configuration' => ServiceTemplateData::normalizeConfiguration([
                'name' => fake()->company().' Status',
                'interval_seconds' => fake()->randomElement(array_keys(Service::intervalOptions())),
                'expect_type' => fake()->randomElement([null, Service::EXPECT_TEXT, Service::EXPECT_REGEX]),
                'expect_value' => fake()->randomElement([null, 'All systems operational', '/healthy/i']),
                'service_group_ids' => [],
                'recipient_group_ids' => [],
                'recipient_ids' => [],
            ]),
        ];
    }
}
