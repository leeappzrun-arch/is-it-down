<?php

namespace Tests\Feature;

use App\Livewire\AiAssistant\Widget;
use App\Models\AiAssistantSetting;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_is_not_rendered_when_the_assistant_is_not_configured(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Open AI assistant');
    }

    public function test_widget_is_rendered_when_the_assistant_is_configured(): void
    {
        AiAssistantSetting::factory()->configured()->create([
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Open AI assistant');
    }

    public function test_admin_users_can_create_a_user_through_the_ai_widget(): void
    {
        $admin = User::factory()->admin()->create();

        AiAssistantSetting::factory()->configured()->create([
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => '',
                            'tool_calls' => [[
                                'id' => 'call_create_user',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'manage_user',
                                    'arguments' => json_encode([
                                        'action' => 'create',
                                        'name' => 'Taylor User',
                                        'email' => 'taylor@example.com',
                                        'password' => 'password123',
                                        'role' => User::ROLE_USER,
                                    ]),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Taylor User has been created.',
                        ],
                    ]],
                ]),
        ]);

        $this->actingAs($admin);

        Livewire::test(Widget::class)
            ->set('draft', 'Create a standard user named Taylor User with email taylor@example.com.')
            ->call('sendMessage')
            ->assertSet('messages.2.content', 'Taylor User has been created.');

        $this->assertDatabaseHas('users', [
            'email' => 'taylor@example.com',
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_standard_users_cannot_mutate_admin_managed_resources_through_the_ai_widget(): void
    {
        $user = User::factory()->create();

        AiAssistantSetting::factory()->configured()->create([
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => '',
                            'tool_calls' => [[
                                'id' => 'call_manage_user_denied',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'manage_user',
                                    'arguments' => json_encode([
                                        'action' => 'create',
                                        'name' => 'Blocked User',
                                        'email' => 'blocked@example.com',
                                        'password' => 'password123',
                                        'role' => User::ROLE_USER,
                                    ]),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'You do not have permission to create users from this account.',
                        ],
                    ]],
                ]),
        ]);

        $this->actingAs($user);

        Livewire::test(Widget::class)
            ->set('draft', 'Create a user called Blocked User.')
            ->call('sendMessage')
            ->assertSet('messages.2.content', 'You do not have permission to create users from this account.');

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_standard_users_can_still_ask_about_a_service_status(): void
    {
        $user = User::factory()->create();

        Service::factory()->currentlyDown()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com',
            'last_check_reason' => 'Expected HTTP 200 response but received 503.',
            'last_status_changed_at' => now()->subMinutes(5),
        ]);

        AiAssistantSetting::factory()->configured()->create([
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => '',
                            'tool_calls' => [[
                                'id' => 'call_inspect_service',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'inspect_service',
                                    'arguments' => json_encode([
                                        'identifier' => 'Billing API',
                                    ]),
                                ],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Billing API is currently down and the latest check received HTTP 503.',
                        ],
                    ]],
                ]),
        ]);

        $this->actingAs($user);

        Livewire::test(Widget::class)
            ->set('draft', 'Why is Billing API down?')
            ->call('sendMessage')
            ->assertSet('messages.2.content', 'Billing API is currently down and the latest check received HTTP 503.');
    }

    public function test_widget_state_persists_between_component_mounts(): void
    {
        $user = User::factory()->create();

        AiAssistantSetting::factory()->configured()->create([
            'settings_key' => AiAssistantSetting::DEFAULT_SETTINGS_KEY,
        ]);

        $this->actingAs($user);

        Livewire::test(Widget::class)
            ->call('toggleOpen')
            ->set('messages', [
                ['role' => 'assistant', 'content' => 'Welcome back.'],
                ['role' => 'user', 'content' => 'Keep this conversation.'],
            ]);

        Livewire::test(Widget::class)
            ->assertSet('isOpen', true)
            ->assertSet('messages.1.content', 'Keep this conversation.');
    }
}
