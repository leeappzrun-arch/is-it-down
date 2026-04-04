<?php

namespace Database\Factories;

use App\Models\AiAssistantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantSetting>
 */
class AiAssistantSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
            'is_enabled' => false,
            'provider_url' => null,
            'api_key' => null,
            'model' => 'gpt-4o-mini',
            'request_timeout_seconds' => 30,
            'system_prompt' => null,
        ];
    }

    /**
     * Indicate that the assistant is fully configured and enabled.
     */
    public function configured(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => true,
            'provider_url' => 'https://api.openai.com/v1/chat/completions',
            'api_key' => 'test-api-key',
            'model' => 'gpt-4o-mini',
            'request_timeout_seconds' => 30,
        ]);
    }
}
