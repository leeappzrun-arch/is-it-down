<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use App\Models\ServiceDowntime;
use Carbon\CarbonInterface;

class ServiceDowntimeRecorder
{
    public function __construct(
        private readonly WebsiteScreenshotter $websiteScreenshotter,
        private readonly OutageAnalyzer $outageAnalyzer,
    ) {}

    /**
     * Record the latest monitoring result against the downtime history.
     */
    public function record(
        Service $service,
        ?string $previousStatus,
        ServiceCheckResult $result,
        CarbonInterface $checkedAt,
    ): ?ServiceDowntime {
        if ($result->status === Service::STATUS_DOWN) {
            return $this->recordDowntime($service, $previousStatus, $result, $checkedAt);
        }

        if ($previousStatus === Service::STATUS_DOWN) {
            return $this->resolveDowntime($service, $result, $checkedAt);
        }

        return null;
    }

    /**
     * Record or update an active downtime incident.
     */
    private function recordDowntime(
        Service $service,
        ?string $previousStatus,
        ServiceCheckResult $result,
        CarbonInterface $checkedAt,
    ): ServiceDowntime {
        $downtime = $service->downtimes()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (! $downtime instanceof ServiceDowntime || $previousStatus !== Service::STATUS_DOWN) {
            $downtime = $service->downtimes()->create([
                'started_at' => $checkedAt,
                'started_reason' => $result->reason,
                'latest_reason' => $result->reason,
                'started_response_code' => $result->responseCode,
                'latest_response_code' => $result->responseCode,
                'last_checked_at' => $checkedAt,
                'last_check_attempts' => $result->attemptCount,
            ]);
        } else {
            $downtime->forceFill([
                'latest_reason' => $result->reason,
                'latest_response_code' => $result->responseCode,
                'last_checked_at' => $checkedAt,
                'last_check_attempts' => $result->attemptCount,
            ])->save();
        }

        if (! $downtime->hasScreenshot() && $result->connectionSucceeded) {
            $capture = $this->websiteScreenshotter->capture($service, $downtime);

            if ($capture !== null) {
                $downtime->forceFill([
                    'screenshot_disk' => $capture['disk'],
                    'screenshot_path' => $capture['path'],
                    'screenshot_captured_at' => $checkedAt,
                ])->save();
            }
        }

        if (blank($downtime->ai_summary)) {
            $analysis = $this->outageAnalyzer->analyze($service, $result, $downtime);

            if (filled($analysis)) {
                $downtime->forceFill([
                    'ai_summary' => $analysis,
                    'ai_summary_created_at' => $checkedAt,
                ])->save();
            }
        }

        return $downtime->fresh();
    }

    /**
     * Resolve the active downtime incident when the service recovers.
     */
    private function resolveDowntime(Service $service, ServiceCheckResult $result, CarbonInterface $checkedAt): ?ServiceDowntime
    {
        $downtime = $service->downtimes()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (! $downtime instanceof ServiceDowntime) {
            return null;
        }

        $downtime->forceFill([
            'ended_at' => $checkedAt,
            'recovery_reason' => $result->reason,
            'recovery_response_code' => $result->responseCode,
        ])->save();

        return $downtime->fresh();
    }
}
