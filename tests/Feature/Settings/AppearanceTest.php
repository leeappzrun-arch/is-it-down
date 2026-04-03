<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_visit_the_appearance_settings_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('appearance.edit'));

        $response->assertOk();
        $response->assertSeeText('Appearance');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('appearance.edit'));

        $response->assertRedirect(route('login'));
    }
}
