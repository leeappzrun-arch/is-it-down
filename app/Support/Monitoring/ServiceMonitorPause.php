<?php

namespace App\Support\Monitoring;

class ServiceMonitorPause
{
    /**
     * Pause briefly before retrying a failed service check.
     */
    public function pause(): void
    {
        $delaySeconds = max(0, (int) config('services.monitoring.failure_retry_delay_seconds', 3));

        if ($delaySeconds === 0) {
            return;
        }

        sleep($delaySeconds);
    }
}
