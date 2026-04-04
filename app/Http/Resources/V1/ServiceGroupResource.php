<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceGroupResource extends JsonResource
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
            'services_count' => $this->whenCounted('services'),
            'recipients_count' => $this->whenCounted('recipients'),
            'recipient_groups_count' => $this->whenCounted('recipientGroups'),
            'services' => $this->whenLoaded('services', fn (): array => $this->services
                ->map(fn ($service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'url' => $service->url,
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
            'recipient_groups' => $this->whenLoaded('recipientGroups', fn (): array => $this->recipientGroups
                ->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                ])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
