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
        if ($result->connectionSucceeded && $result->status === Service::STATUS_UP && $previousStatus !== Service::STATUS_DOWN) {
            $this->storeLatestServiceScreenshot($service, $checkedAt);
        }

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
                'started_response_headers' => $result->responseHeaders === [] ? null : $result->responseHeaders,
                'latest_response_code' => $result->responseCode,
                'latest_response_headers' => $result->responseHeaders === [] ? null : $result->responseHeaders,
                'last_checked_at' => $checkedAt,
                'last_check_attempts' => $result->attemptCount,
            ]);
        } else {
            $downtime->forceFill([
                'latest_reason' => $result->reason,
                'latest_response_code' => $result->responseCode,
                'latest_response_headers' => $result->responseHeaders === [] ? null : $result->responseHeaders,
                'last_checked_at' => $checkedAt,
                'last_check_attempts' => $result->attemptCount,
            ])->save();
        }

        if ($result->connectionSucceeded) {
            $pngContents = $this->storeLatestServiceScreenshot($service, $checkedAt);

            if ($pngContents !== null) {
                $downtimeCapture = $this->websiteScreenshotter->storeForDowntime($service, $downtime, $pngContents);

                $downtime->forceFill([
                    'screenshot_disk' => $downtimeCapture['disk'],
                    'screenshot_path' => $downtimeCapture['path'],
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

        if ($result->connectionSucceeded) {
            $this->storeLatestServiceScreenshot($service, $checkedAt);
        }

        $downtime->forceFill([
            'ended_at' => $checkedAt,
            'recovery_reason' => $result->reason,
            'recovery_response_code' => $result->responseCode,
        ])->save();

        return $downtime->fresh();
    }

    /**
     * Capture and store the latest screenshot for the service.
     */
    private function storeLatestServiceScreenshot(Service $service, CarbonInterface $checkedAt): ?string
    {
        $pngContents = $this->websiteScreenshotter->capture($service);

        if ($pngContents === null) {
            return null;
        }

        $latestServiceCapture = $this->websiteScreenshotter->storeLatestForService($service, $pngContents);

        $service->forceFill([
            'last_screenshot_disk' => $latestServiceCapture['disk'],
            'last_screenshot_path' => $latestServiceCapture['path'],
            'last_screenshot_captured_at' => $checkedAt,
        ])->save();

        return $pngContents;
    }
}
