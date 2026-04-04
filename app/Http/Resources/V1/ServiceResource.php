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
            'expect_type' => $this->expect_type,
            'expect_value' => $this->expect_value,
            'current_status' => $this->current_status,
            'monitoring_status_label' => $this->monitoringStatusLabel(),
            'last_response_code' => $this->last_response_code,
            'last_check_reason' => $this->last_check_reason,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'next_check_at' => $this->next_check_at?->toIso8601String(),
            'last_status_changed_at' => $this->last_status_changed_at?->toIso8601String(),
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
