<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DocumentationPagesTest extends TestCase
{
    public function test_guests_are_redirected_when_visiting_the_documentation_pages(): void
    {
        $this->get(route('user-guide'))->assertRedirect(route('login'));
        $this->get(route('api-documentation'))->assertRedirect(route('login'));
        $this->get(route('webhook-documentation'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_user_guide(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('user-guide'));

        $response->assertOk();
        $response->assertSeeText('User Guide');
        $response->assertSeeText('Recipient management');
        $response->assertSeeText('Service management');
    }

    public function test_authenticated_users_can_visit_the_api_documentation_page(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('api-documentation'));

        $response->assertOk();
        $response->assertSeeText('API Documentation');
        $response->assertSeeText('API key preparation');
    }

    public function test_authenticated_users_can_visit_the_webhook_documentation_page(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('webhook-documentation'));

        $response->assertOk();
        $response->assertSeeText('Webhook Documentation');
        $response->assertSeeText('Authentication options');
    }

    public function test_authenticated_users_see_documentation_links_in_the_sidebar_layout(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('user-guide'));

        $response->assertOk();
        $response->assertSee('href="'.route('user-guide').'"', false);
        $response->assertSee('href="'.route('api-documentation').'"', false);
        $response->assertSee('href="'.route('webhook-documentation').'"', false);
    }

    private function verifiedUser(): User
    {
        return User::factory()->make([
            'email_verified_at' => now(),
        ]);
    }
}
