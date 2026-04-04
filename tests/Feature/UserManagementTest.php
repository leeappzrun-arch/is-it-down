<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_user_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('users.index'));

        $response->assertOk();
        $response->assertSeeTextInOrder(['Recipients', 'Services', 'Users']);
        $response->assertSeeText('Users');
        $response->assertSee('sticky top-4 z-20', false);
    }

    public function test_non_admin_users_cannot_visit_the_user_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_delete_standard_users_after_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $standardUser = User::factory()->standard()->create([
            'name' => 'Taylor User',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::users.index')
            ->call('confirmUserDeletion', $standardUser->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationUserId', $standardUser->id)
            ->call('deleteConfirmedUser');

        $this->assertDatabaseMissing('users', [
            'id' => $standardUser->id,
        ]);
    }

    public function test_admin_users_cannot_delete_other_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create([
            'name' => 'Protected Admin',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::users.index')
            ->call('confirmUserDeletion', $otherAdmin->id)
            ->assertSet('showDeleteConfirmationModal', false)
            ->assertSet('deleteConfirmationUserId', null)
            ->call('deleteConfirmedUser');

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_admin_users_can_search_the_user_management_table(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->create([
            'name' => 'Alice Support',
            'email' => 'alice@example.com',
        ]);

        User::factory()->create([
            'name' => 'Bob Finance',
            'email' => 'bob@example.com',
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::users.index')
            ->assertSee('Alice Support')
            ->assertSee('Bob Finance')
            ->set('search', 'alice')
            ->assertSee('Alice Support')
            ->assertDontSee('Bob Finance');
    }
}
