<?php

namespace Tests\Feature;

use App\Models\ServiceDowntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneServiceDowntimeHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_old_resolved_downtime_records_and_their_screenshots(): void
    {
        Storage::fake('public');

        config()->set('services.monitoring.downtime_history_retention_days', 90);

        $oldDowntime = ServiceDowntime::factory()->create([
            'ended_at' => now()->subDays(91),
            'screenshot_disk' => 'public',
            'screenshot_path' => 'downtime-screenshots/service-1-downtime-1.png',
        ]);

        $recentDowntime = ServiceDowntime::factory()->create([
            'ended_at' => now()->subDays(30),
            'screenshot_disk' => 'public',
            'screenshot_path' => 'downtime-screenshots/service-2-downtime-2.png',
        ]);

        Storage::disk('public')->put((string) $oldDowntime->screenshot_path, 'old-image');
        Storage::disk('public')->put((string) $recentDowntime->screenshot_path, 'recent-image');

        $this->artisan('monitor:prune-downtime-history')
            ->assertSuccessful()
            ->expectsOutput('Pruned 1 downtime record(s) older than 90 day(s).');

        $this->assertDatabaseMissing('service_downtimes', [
            'id' => $oldDowntime->id,
        ]);

        $this->assertDatabaseHas('service_downtimes', [
            'id' => $recentDowntime->id,
        ]);

        Storage::disk('public')->assertMissing((string) $oldDowntime->screenshot_path);
        Storage::disk('public')->assertExists((string) $recentDowntime->screenshot_path);
    }
}
