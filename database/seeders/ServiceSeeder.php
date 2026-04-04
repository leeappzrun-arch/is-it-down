<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seededAt = now();

        $recipientGroups = RecipientGroup::query()
            ->whereIn('name', ['Operations', 'Leadership', 'Vendors'])
            ->get()
            ->keyBy('name');

        $recipients = Recipient::query()
            ->whereIn('name', ['Operations Inbox', 'PagerDuty Webhook', 'Vendor Status Webhook'])
            ->get()
            ->keyBy('name');

        $serviceGroups = ServiceGroup::query()
            ->whereIn('name', ['Production', 'Stakeholders'])
            ->get()
            ->keyBy('name');

        $this->seedService(
            attributes: [
                'name' => 'Marketing Site',
                'url' => 'https://example.com',
                'interval_seconds' => Service::INTERVAL_1_MINUTE,
                'expect_type' => Service::EXPECT_TEXT,
                'expect_value' => 'All systems operational',
                'current_status' => Service::STATUS_UP,
                'last_response_code' => 200,
                'last_check_reason' => 'Received an HTTP 200 response and the expected text was present.',
                'last_checked_at' => $seededAt->copy()->subSeconds(30),
                'next_check_at' => $seededAt->copy()->addSeconds(30),
                'last_status_changed_at' => $seededAt->copy()->subDay(),
            ],
            recipientIds: [
                $recipients->get('Operations Inbox')?->id,
            ],
            recipientGroupIds: [
                $recipientGroups->get('Leadership')?->id,
            ],
            serviceGroupIds: [
                $serviceGroups->get('Production')?->id,
                $serviceGroups->get('Stakeholders')?->id,
            ],
        );

        $this->seedService(
            attributes: [
                'name' => 'Vendor API',
                'url' => 'https://status.vendor.example.com',
                'interval_seconds' => Service::INTERVAL_5_MINUTES,
                'expect_type' => Service::EXPECT_REGEX,
                'expect_value' => '/status\\s*:\\s*ok/i',
                'current_status' => Service::STATUS_DOWN,
                'last_response_code' => 503,
                'last_check_reason' => 'Expected HTTP 200 response but received 503.',
                'last_checked_at' => $seededAt->copy()->subMinutes(4),
                'next_check_at' => $seededAt->copy()->addMinute(),
                'last_status_changed_at' => $seededAt->copy()->subMinutes(20),
            ],
            recipientIds: [
                $recipients->get('Vendor Status Webhook')?->id,
            ],
            recipientGroupIds: [
                $recipientGroups->get('Vendors')?->id,
            ],
            serviceGroupIds: [
                $serviceGroups->get('Production')?->id,
            ],
        );
    }

    /**
     * Create or update a service and align its assignments.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|null>  $recipientIds
     * @param  array<int, int|null>  $recipientGroupIds
     * @param  array<int, int|null>  $serviceGroupIds
     */
    private function seedService(array $attributes, array $recipientIds, array $recipientGroupIds, array $serviceGroupIds): void
    {
        $service = Service::query()->updateOrCreate(
            ['name' => $attributes['name']],
            $attributes,
        );

        $service->recipients()->sync(array_values(array_filter($recipientIds)));
        $service->recipientGroups()->sync(array_values(array_filter($recipientGroupIds)));
        $service->groups()->sync(array_values(array_filter($serviceGroupIds)));
    }
}
