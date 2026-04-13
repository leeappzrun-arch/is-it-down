<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipientResource extends JsonResource
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
            'endpoint' => $this->endpoint,
            'endpoint_type' => $this->endpointType(),
            'endpoint_target' => $this->endpointTarget(),
            'webhook_auth_type' => $this->webhook_auth_type,
            'webhook_auth_summary' => $this->isWebhookEndpoint() ? $this->webhookAuthenticationSummary() : 'Not required',
            'additional_headers' => $this->configuredAdditionalHeaders(),
            'additional_headers_count' => count($this->configuredAdditionalHeaders()),
            'groups' => $this->whenLoaded('groups', fn (): array => $this->groups
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
