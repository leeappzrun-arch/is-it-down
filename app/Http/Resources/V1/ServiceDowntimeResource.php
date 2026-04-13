<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceDowntimeResource extends JsonResource
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
            'service_id' => $this->service_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'is_ongoing' => $this->isOngoing(),
            'duration_seconds' => $this->durationInSeconds($this->ended_at),
            'duration_human' => $this->durationSummary($this->ended_at),
            'started_reason' => $this->started_reason,
            'latest_reason' => $this->latest_reason,
            'recovery_reason' => $this->recovery_reason,
            'started_response_code' => $this->started_response_code,
            'latest_response_code' => $this->latest_response_code,
            'recovery_response_code' => $this->recovery_response_code,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_check_attempts' => $this->last_check_attempts,
            'screenshot_url' => $this->screenshotUrl(),
            'ai_summary' => $this->ai_summary,
            'service' => $this->whenLoaded('service', fn (): array => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'url' => $this->service->url,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
