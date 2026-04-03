<?php

namespace Database\Factories;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipientGroup>
 */
class RecipientGroupFactory extends Factory
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
     * Attach recipients to the group after it is created.
     */
    public function withRecipients(int $count = 3): static
    {
        return $this->afterCreating(function (RecipientGroup $group) use ($count): void {
            $group->recipients()->syncWithoutDetaching(
                Recipient::factory()->count($count)->create()->modelKeys()
            );
        });
    }
}
