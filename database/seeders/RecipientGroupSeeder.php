<?php

namespace Database\Seeders;

use App\Models\RecipientGroup;
use Illuminate\Database\Seeder;

class RecipientGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            'Operations',
            'Leadership',
            'Vendors',
        ])->each(fn (string $name): RecipientGroup => RecipientGroup::query()->updateOrCreate(
            ['name' => $name],
            [],
        ));
    }
}
