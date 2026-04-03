<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_login_screen_from_the_home_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('login.store'), false);
    }

    public function test_authenticated_users_are_redirected_from_the_home_page_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
