<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use Illuminate\Database\Seeder;

class RecipientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = RecipientGroup::query()
            ->whereIn('name', ['Operations', 'Leadership', 'Vendors'])
            ->get()
            ->keyBy('name');

        $this->seedRecipient(
            attributes: [
                'name' => 'Operations Inbox',
                'endpoint' => 'mailto://ops@example.com',
                'webhook_auth_type' => Recipient::WEBHOOK_AUTH_NONE,
                'webhook_auth_username' => null,
                'webhook_auth_password' => null,
                'webhook_auth_token' => null,
                'webhook_auth_header_name' => null,
                'webhook_auth_header_value' => null,
            ],
            groupIds: [
                $groups->get('Operations')?->id,
                $groups->get('Leadership')?->id,
            ],
        );

        $this->seedRecipient(
            attributes: [
                'name' => 'PagerDuty Webhook',
                'endpoint' => 'webhook://hooks.example.com/services/pagerduty',
                'webhook_auth_type' => Recipient::WEBHOOK_AUTH_BEARER,
                'webhook_auth_username' => null,
                'webhook_auth_password' => null,
                'webhook_auth_token' => 'seeded-pagerduty-token',
                'webhook_auth_header_name' => null,
                'webhook_auth_header_value' => null,
            ],
            groupIds: [
                $groups->get('Operations')?->id,
            ],
        );

        $this->seedRecipient(
            attributes: [
                'name' => 'Vendor Status Webhook',
                'endpoint' => 'webhook://status.vendor.example.com/hooks/inbound',
                'webhook_auth_type' => Recipient::WEBHOOK_AUTH_HEADER,
                'webhook_auth_username' => null,
                'webhook_auth_password' => null,
                'webhook_auth_token' => null,
                'webhook_auth_header_name' => 'X-Vendor-Key',
                'webhook_auth_header_value' => 'seeded-vendor-header-value',
            ],
            groupIds: [
                $groups->get('Vendors')?->id,
            ],
        );
    }

    /**
     * Create or update a recipient and align its group assignments.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|null>  $groupIds
     */
    private function seedRecipient(array $attributes, array $groupIds): void
    {
        $recipient = Recipient::query()->updateOrCreate(
            ['name' => $attributes['name']],
            $attributes,
        );

        $recipient->groups()->sync(array_values(array_filter($groupIds)));
    }
}
