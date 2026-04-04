<?php

namespace App\Models;

use Database\Factories\AiAssistantSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'settings_key',
    'is_enabled',
    'provider_url',
    'api_key',
    'model',
    'request_timeout_seconds',
    'system_prompt',
])]
class AiAssistantSetting extends Model
{
    /** @use HasFactory<AiAssistantSettingFactory> */
    use HasFactory;

    public const DEFAULT_SETTINGS_KEY = 'default';

    /**
     * Get the singleton AI assistant settings row.
     */
    public static function current(): self
    {
        if (! Schema::hasTable('ai_assistant_settings')) {
            return new self([
                'settings_key' => self::DEFAULT_SETTINGS_KEY,
                ...self::defaults(),
            ]);
        }

        return self::query()->firstOrCreate(
            ['settings_key' => self::DEFAULT_SETTINGS_KEY],
            self::defaults(),
        );
    }

    /**
     * Get the configured AI assistant settings row if the assistant is enabled.
     */
    public static function enabled(): ?self
    {
        if (! Schema::hasTable('ai_assistant_settings')) {
            return null;
        }

        $settings = self::query()
            ->where('settings_key', self::DEFAULT_SETTINGS_KEY)
            ->first();

        if ($settings === null || ! $settings->isConfigured()) {
            return null;
        }

        return $settings;
    }

    /**
     * Get the default settings values.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'is_enabled' => false,
            'provider_url' => null,
            'api_key' => null,
            'model' => 'gpt-4o-mini',
            'request_timeout_seconds' => 30,
            'system_prompt' => null,
        ];
    }

    /**
     * Determine whether the assistant can be shown to users.
     */
    public function isConfigured(): bool
    {
        return (bool) $this->is_enabled
            && filled($this->provider_url)
            && filled($this->api_key)
            && filled($this->model);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'api_key' => 'encrypted',
            'request_timeout_seconds' => 'integer',
        ];
    }
}
