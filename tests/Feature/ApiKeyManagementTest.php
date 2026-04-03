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
            ->set('ownerType', ApiKey::OWNER_USER)
            ->set('expirationOption', '1_year')
            ->set('selectedPermissions', ['users:read', 'recipients:write'])
            ->call('createApiKey');

        $response->assertHasNoErrors();
        $response->assertSet('newlyCreatedToken', fn (string $value): bool => str_starts_with($value, 'iid_'));

        $apiKey = ApiKey::query()->where('name', 'Primary admin key')->first();

        $this->assertNotNull($apiKey);
        $this->assertSame(ApiKey::OWNER_USER, $apiKey->owner_type);
        $this->assertSame($admin->id, $apiKey->user_id);
        $this->assertNull($apiKey->service_name);
        $this->assertSame(['users:read', 'recipients:write'], $apiKey->permissions);
        $this->assertNotNull($apiKey->expires_at);
    }

    public function test_admin_users_can_create_a_service_api_key_without_an_expiration_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $response = Livewire::test('pages::api-keys.index')
            ->set('name', 'Status page')
            ->set('ownerType', ApiKey::OWNER_SERVICE)
            ->set('serviceName', 'Status page worker')
            ->set('expirationOption', 'never')
            ->set('selectedPermissions', ['recipients:read'])
            ->call('createApiKey');

        $response->assertHasNoErrors();

        $this->assertDatabaseHas('api_keys', [
            'name' => 'Status page',
            'owner_type' => ApiKey::OWNER_SERVICE,
            'service_name' => 'Status page worker',
            'user_id' => null,
            'expires_at' => null,
        ]);
    }

    public function test_service_api_keys_require_a_service_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::api-keys.index')
            ->set('name', 'Broken key')
            ->set('ownerType', ApiKey::OWNER_SERVICE)
            ->set('serviceName', '')
            ->set('selectedPermissions', ['users:read'])
            ->call('createApiKey')
            ->assertHasErrors(['serviceName']);
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
}
