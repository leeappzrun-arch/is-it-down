<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_api_key_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('api-keys.index'));

        $response->assertOk();
        $response->assertSeeText('API Keys');
        $response->assertSee('sticky top-4 z-20', false);
    }

    public function test_non_admin_users_cannot_visit_the_api_key_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('api-keys.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_create_a_user_owned_api_key(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin);

        $response = Livewire::test('pages::api-keys.index')
            ->set('name', 'Primary admin key')
            ->set('expirationOption', '1_year')
            ->set('selectedPermissions', ['users:read', 'recipients:write'])
            ->call('createApiKey');

        $response->assertHasNoErrors();
        $response->assertSet('showNewApiKeyModal', true);
        $response->assertSet('newlyCreatedToken', fn (string $value): bool => str_starts_with($value, 'iid_'));
        $response->assertSee('This API key will not be shown again after you close this modal.');

        $response->set('showNewApiKeyModal', false)
            ->assertSet('newlyCreatedToken', null);

        $apiKey = ApiKey::query()->where('name', 'Primary admin key')->first();

        $this->assertNotNull($apiKey);
        $this->assertSame($admin->id, $apiKey->user_id);
        $this->assertSame(['users:read', 'recipients:write'], $apiKey->permissions);
        $this->assertNotNull($apiKey->expires_at);
    }

    public function test_api_keys_always_belong_to_the_current_admin_account(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::api-keys.index')
            ->assertSee('This key will be assigned to admin@example.com.')
            ->set('name', 'Read only key')
            ->set('expirationOption', 'never')
            ->set('selectedPermissions', ['recipients:read'])
            ->call('createApiKey');

        $this->assertDatabaseHas('api_keys', [
            'name' => 'Read only key',
            'user_id' => $admin->id,
            'expires_at' => null,
        ]);
    }

    public function test_admin_users_can_revoke_an_api_key(): void
    {
        $admin = User::factory()->admin()->create();
        $apiKey = ApiKey::factory()->for($admin, 'creator')->for($admin)->create();

        $this->actingAs($admin);

        Livewire::test('pages::api-keys.index')
            ->call('revokeApiKey', $apiKey->id)
            ->assertHasNoErrors();

        $this->assertNotNull($apiKey->refresh()->revoked_at);
    }

    public function test_admin_users_can_search_api_keys(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        ApiKey::factory()->for($admin, 'creator')->for($admin)->create([
            'name' => 'Primary monitoring key',
            'permissions' => ['services:read'],
        ]);

        $anotherUser = User::factory()->create([
            'name' => 'Payroll User',
            'email' => 'payroll@example.com',
        ]);

        ApiKey::factory()->for($admin, 'creator')->for($anotherUser)->create([
            'name' => 'Payroll admin tooling',
            'permissions' => ['users:read'],
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::api-keys.index')
            ->assertSee('Primary monitoring key')
            ->assertSee('Payroll admin tooling')
            ->set('search', 'payroll')
            ->assertSee('Payroll admin tooling')
            ->assertDontSee('Primary monitoring key');
    }

    public function test_api_key_listing_shows_last_used_details(): void
    {
        $admin = User::factory()->admin()->create();

        ApiKey::factory()->for($admin, 'creator')->for($admin)->create([
            'name' => 'Unused key',
            'last_used_at' => null,
        ]);

        ApiKey::factory()->for($admin, 'creator')->for($admin)->create([
            'name' => 'Recently used key',
            'last_used_at' => now()->subHour(),
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::api-keys.index')
            ->assertSee('Last Used')
            ->assertSee('Never used')
            ->assertSee('hour');
    }
}
