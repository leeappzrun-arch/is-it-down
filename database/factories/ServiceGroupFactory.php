<?php

namespace Database\Factories;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\ServiceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceGroup>
 */
class ServiceGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * Attach direct recipients after the group is created.
     */
    public function withRecipients(int $count = 2): static
    {
        return $this->afterCreating(function (ServiceGroup $serviceGroup) use ($count): void {
            $serviceGroup->recipients()->syncWithoutDetaching(
                Recipient::factory()->count($count)->create()->modelKeys()
            );
        });
    }

    /**
     * Attach recipient groups after the group is created.
     */
    public function withRecipientGroups(int $count = 1): static
    {
        return $this->afterCreating(function (ServiceGroup $serviceGroup) use ($count): void {
            $serviceGroup->recipientGroups()->syncWithoutDetaching(
                RecipientGroup::factory()->count($count)->create()->modelKeys()
            );
        });
    }
}
