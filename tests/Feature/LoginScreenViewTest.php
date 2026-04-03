<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginScreenViewTest extends TestCase
{
    public function test_login_screen_displays_the_larger_logo_icon(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('class="fill-current text-black dark:text-white size-16"', false);
    }

    public function test_login_screen_prefers_the_svg_favicon(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false);
    }

    public function test_svg_favicon_matches_the_app_logo_icon_geometry(): void
    {
        $favicon = file_get_contents(public_path('favicon.svg'));

        $this->assertIsString($favicon);
        $this->assertStringContainsString('d="M47.3 18.7A20 20 0 1 0 52 32"', $favicon);
        $this->assertStringContainsString('d="M20 33.5L28 41.5L44 23.5"', $favicon);
        $this->assertStringContainsString('cx="49.5" cy="18" r="3.5"', $favicon);
    }
}
