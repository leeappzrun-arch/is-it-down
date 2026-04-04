<?php

namespace Database\Seeders;

use App\Models\AiAssistantSetting;
use Illuminate\Database\Seeder;

class AiAssistantSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AiAssistantSetting::query()->updateOrCreate(
            ['settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY],
            AiAssistantSetting::defaults(),
        );
    }
}
