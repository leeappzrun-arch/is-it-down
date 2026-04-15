<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'interval_seconds' => $this->interval_seconds,
            'interval_label' => $this->intervalLabel(),
            'monitoring_method' => $this->monitoringMethod(),
            'monitoring_method_label' => $this->monitoringMethodLabel(),
            'expect_type' => $this->expect_type,
            'expect_value' => $this->expect_value,
            'additional_headers' => $this->configuredAdditionalHeaders(),
            'additional_headers_count' => count($this->configuredAdditionalHeaders()),
            'ssl_expiry_notifications_enabled' => (bool) $this->ssl_expiry_notifications_enabled,
            'current_status' => $this->current_status,
            'monitoring_status_label' => $this->monitoringStatusLabel(),
            'uptime_percentage_last_30_days' => $this->uptimePercentageForDays(30),
            'last_response_code' => $this->last_response_code,
            'last_response_headers' => $this->lastResponseHeaders(),
            'last_check_reason' => $this->last_check_reason,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'latest_screenshot_url' => $this->latestScreenshotUrl(),
            'last_screenshot_captured_at' => $this->last_screenshot_captured_at?->toIso8601String(),
            'next_check_at' => $this->next_check_at?->toIso8601String(),
            'last_status_changed_at' => $this->last_status_changed_at?->toIso8601String(),
            'last_ssl_expiry_notification_sent_at' => $this->last_ssl_expiry_notification_sent_at?->toIso8601String(),
            'current_downtime' => $this->whenLoaded('currentDowntime', fn (): ?array => $this->currentDowntime === null ? null : (new ServiceDowntimeResource($this->currentDowntime))->toArray($request)),
            'recent_downtimes' => $this->whenLoaded('downtimes', fn (): array => $this->recentDowntimes()
                ->map(fn ($downtime): array => (new ServiceDowntimeResource($downtime))->toArray($request))
                ->all()),
            'groups' => $this->whenLoaded('groups', fn (): array => $this->groups
                ->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                ])
                ->all()),
            'recipient_groups' => $this->whenLoaded('recipientGroups', fn (): array => $this->recipientGroups
                ->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                ])
                ->all()),
            'recipients' => $this->whenLoaded('recipients', fn (): array => $this->recipients
                ->map(fn ($recipient): array => [
                    'id' => $recipient->id,
                    'name' => $recipient->name,
                    'endpoint_type' => $recipient->endpointType(),
                    'endpoint_target' => $recipient->endpointTarget(),
                ])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
