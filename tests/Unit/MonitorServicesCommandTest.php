<?php

namespace Tests\Unit;

use App\Console\Commands\MonitorServicesCommand;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MonitorServicesCommandTest extends TestCase
{
    public function test_it_applies_positive_schedule_jitter_within_the_configured_range(): void
    {
        config()->set('services.monitoring.schedule_jitter_max_seconds', 15);

        $checkedAt = CarbonImmutable::parse('2026-04-04 10:21:00');
        $service = new Service([
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
        ]);

        $command = app(MonitorServicesCommand::class);
        $nextCheckAt = (fn () => $this->nextCheckAt($checkedAt, $service))
            ->call($command);

        $this->assertTrue($nextCheckAt->greaterThanOrEqualTo($checkedAt->addMinute()));
        $this->assertTrue($nextCheckAt->lessThanOrEqualTo($checkedAt->addMinute()->addSeconds(15)));
    }
}
