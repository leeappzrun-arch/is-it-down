<?php

namespace App\Http\Resources\V1;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceTemplateResource extends JsonResource
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
            'service_name' => $this->serviceName(),
            'interval_seconds' => $this->intervalSeconds(),
            'interval_label' => $this->intervalLabel(),
            'expect_type' => $this->expectType() === Service::EXPECT_NONE ? null : $this->expectType(),
            'expect_value' => $this->expectValue(),
            'additional_headers' => $this->configuredAdditionalHeaders(),
            'additional_headers_count' => count($this->configuredAdditionalHeaders()),
            'ssl_expiry_notifications_enabled' => $this->sslExpiryNotificationsEnabled(),
            'service_group_ids' => $this->selectedServiceGroupIds(),
            'recipient_group_ids' => $this->selectedRecipientGroupIds(),
            'recipient_ids' => $this->selectedRecipientIds(),
            'service_groups_count' => count($this->selectedServiceGroupIds()),
            'recipient_groups_count' => count($this->selectedRecipientGroupIds()),
            'recipients_count' => count($this->selectedRecipientIds()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
