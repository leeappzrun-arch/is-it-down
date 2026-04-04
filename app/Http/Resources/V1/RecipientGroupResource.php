<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipientGroupResource extends JsonResource
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
            'recipients_count' => $this->whenCounted('recipients'),
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
