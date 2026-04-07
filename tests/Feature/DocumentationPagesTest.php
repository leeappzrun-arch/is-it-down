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
        $this->get(route('api-playground'))->assertRedirect(route('login'));
        $this->get(route('webhook-documentation'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_user_guide(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('user-guide'));

        $response->assertOk();
        $response->assertSeeText('User Guide');
        $response->assertSeeText('On this page');
        $response->assertSeeText('Recipient management');
        $response->assertSeeText('Service management');
        $response->assertSeeText('Template management');
        $response->assertSee('href="#getting-started"', false);
        $response->assertSee('href="#service-management"', false);
        $response->assertSee('href="#template-management"', false);
        $response->assertSee('xl:grid-cols-2', false);
    }

    public function test_authenticated_users_can_visit_the_api_documentation_page(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('api-documentation'));

        $response->assertOk();
        $response->assertSeeText('API Documentation');
        $response->assertSeeText('Versioned REST endpoints authenticated with user-owned API keys.');
        $response->assertSeeText('On this page');
        $response->assertSeeText('List recipients');
        $response->assertSeeText('Search endpoints');
        $response->assertSeeText('More details');
        $response->assertSee('href="#authentication"', false);
        $response->assertSee('href="#endpoint-catalog"', false);
        $response->assertSee('wire:model.live.debounce.300ms="search"', false);
        $response->assertSee('sticky top-4 z-20', false);
        $response->assertSee('x-on:scroll.window.throttle.50ms="updateStickyState()"', false);
        $response->assertSee('shadow-lg shadow-zinc-900/10 dark:shadow-black/30', false);
        $response->assertSee('xl:grid-cols-3', false);
        $response->assertSee('id="endpoint-catalog" class="scroll-mt-24 mt-6 space-y-4"', false);
        $response->assertSee('<details', false);
    }

    public function test_authenticated_users_can_visit_the_api_playground_page(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('api-playground'));

        $response->assertOk();
        $response->assertSeeText('API Playground');
        $response->assertSeeText('Request setup');
    }

    public function test_authenticated_users_can_visit_the_webhook_documentation_page(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('webhook-documentation'));

        $response->assertOk();
        $response->assertSeeText('Webhook Documentation');
        $response->assertSeeText('On this page');
        $response->assertSeeText('Authentication options');
        $response->assertSee('href="#payload-shape"', false);
        $response->assertSee('href="#security-and-storage"', false);
        $response->assertSee('xl:grid-cols-2', false);
    }

    public function test_authenticated_users_see_documentation_links_in_the_sidebar_layout(): void
    {
        $response = $this->actingAs($this->verifiedUser())
            ->get(route('user-guide'));

        $response->assertOk();
        $response->assertSee('href="'.route('user-guide').'"', false);
        $response->assertSee('href="'.route('api-documentation').'"', false);
        $response->assertSee('href="'.route('api-playground').'"', false);
        $response->assertSee('href="'.route('webhook-documentation').'"', false);
    }

    private function verifiedUser(): User
    {
        return User::factory()->make([
            'email_verified_at' => now(),
        ]);
    }
}
