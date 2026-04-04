<?php

namespace Tests\Feature;

use App\Models\AiAssistantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_ai_assistant_settings_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('ai-assistant.edit'));

        $response->assertOk();
        $response->assertSeeText('Dave');
        $response->assertSeeText('Enable Dave');
        $response->assertSeeText('Provider URL');
    }

    public function test_non_admin_users_cannot_visit_the_ai_assistant_settings_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('ai-assistant.edit'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_save_ai_assistant_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test('pages::settings.ai-assistant')
            ->set('isEnabled', true)
            ->set('providerUrl', 'https://api.openai.com/v1/chat/completions')
            ->set('apiKey', 'super-secret-key')
            ->set('model', 'gpt-4o-mini')
            ->set('requestTimeoutSeconds', 45)
            ->set('systemPrompt', 'Always be careful with production changes.')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $settings = AiAssistantSetting::current()->fresh();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame('https://api.openai.com/v1/chat/completions', $settings->provider_url);
        $this->assertSame('super-secret-key', $settings->api_key);
        $this->assertSame('gpt-4o-mini', $settings->model);
        $this->assertSame(45, $settings->request_timeout_seconds);
        $this->assertSame('Always be careful with production changes.', $settings->system_prompt);
    }

    public function test_existing_api_key_is_preserved_when_admin_leaves_the_api_key_blank(): void
    {
        $admin = User::factory()->admin()->create();

        AiAssistantSetting::factory()->create([
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
            'is_enabled' => true,
            'provider_url' => 'https://api.openai.com/v1/chat/completions',
            'api_key' => 'existing-key',
            'model' => 'gpt-4o-mini',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::settings.ai-assistant')
            ->set('providerUrl', 'https://example.com/v1/chat/completions')
            ->set('apiKey', '')
            ->set('model', 'gpt-4.1-mini')
            ->set('requestTimeoutSeconds', 60)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $settings = AiAssistantSetting::current()->fresh();

        $this->assertSame('existing-key', $settings->api_key);
        $this->assertSame('https://example.com/v1/chat/completions', $settings->provider_url);
        $this->assertSame('gpt-4.1-mini', $settings->model);
        $this->assertSame(60, $settings->request_timeout_seconds);
    }
}
