<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_user_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('users.index'));

        $response->assertOk();
        $response->assertSeeTextInOrder(['Recipients', 'Users']);
        $response->assertSeeText('Users');
    }

    public function test_non_admin_users_cannot_visit_the_user_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('users.index'));

        $response->assertForbidden();
    }
}
