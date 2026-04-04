<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

class WardenIntegrationTest extends TestCase
{
    public function test_warden_commands_are_registered(): void
    {
        $commands = app(Kernel::class)->all();

        $this->assertArrayHasKey('warden:audit', $commands);
        $this->assertArrayHasKey('warden:schedule', $commands);
        $this->assertArrayHasKey('warden:syntax', $commands);
    }

    public function test_warden_configuration_matches_project_defaults(): void
    {
        $this->assertSame(config('app.name'), config('warden.app_name'));
        $this->assertFalse(config('warden.schedule.enabled'));
        $this->assertFalse(config('warden.history.enabled'));
        $this->assertSame(['APP_KEY'], config('warden.sensitive_keys'));
    }
}
