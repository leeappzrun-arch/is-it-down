<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceDowntime;
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
                'monitoring_method' => Service::MONITOR_HTTP,
                'expect_type' => Service::EXPECT_TEXT,
                'expect_value' => 'All systems operational',
                'additional_headers' => [
                    ['name' => 'X-Monitor', 'value' => 'marketing'],
                ],
                'ssl_expiry_notifications_enabled' => true,
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
                'monitoring_method' => Service::MONITOR_BROWSER,
                'expect_type' => Service::EXPECT_REGEX,
                'expect_value' => '/status\\s*:\\s*ok/i',
                'additional_headers' => [
                    ['name' => 'X-Vendor-Monitor', 'value' => 'vendor-api'],
                ],
                'ssl_expiry_notifications_enabled' => false,
                'current_status' => Service::STATUS_DOWN,
                'last_response_code' => 503,
                'last_check_reason' => 'The service still appeared down after retrying 3 seconds later. Expected HTTP 200 response but received 503.',
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

        $marketingSite = Service::query()->where('name', 'Marketing Site')->first();
        $vendorApi = Service::query()->where('name', 'Vendor API')->first();

        if ($marketingSite instanceof Service) {
            ServiceDowntime::query()->updateOrCreate(
                [
                    'service_id' => $marketingSite->id,
                    'started_at' => $seededAt->copy()->subDays(2)->subMinutes(18),
                ],
                [
                    'ended_at' => $seededAt->copy()->subDays(2)->subMinutes(10),
                    'started_reason' => 'The service still appeared down after retrying 3 seconds later. Expected HTTP 200 response but received 503.',
                    'latest_reason' => 'Expected HTTP 200 response but received 503.',
                    'recovery_reason' => 'Received an HTTP 200 response and the expected text was present.',
                    'started_response_code' => 503,
                    'latest_response_code' => 503,
                    'recovery_response_code' => 200,
                    'last_checked_at' => $seededAt->copy()->subDays(2)->subMinutes(10),
                    'last_check_attempts' => 2,
                    'ai_summary' => 'The upstream likely served a maintenance or origin error page for a short period.',
                ],
            );
        }

        if ($vendorApi instanceof Service) {
            ServiceDowntime::query()->updateOrCreate(
                [
                    'service_id' => $vendorApi->id,
                    'started_at' => $seededAt->copy()->subMinutes(20),
                ],
                [
                    'ended_at' => null,
                    'started_reason' => 'The service still appeared down after retrying 3 seconds later. Expected HTTP 200 response but received 503.',
                    'latest_reason' => 'Expected HTTP 200 response but received 503.',
                    'recovery_reason' => null,
                    'started_response_code' => 503,
                    'latest_response_code' => 503,
                    'recovery_response_code' => null,
                    'last_checked_at' => $seededAt->copy()->subMinutes(4),
                    'last_check_attempts' => 2,
                    'ai_summary' => 'The upstream appears to still be unavailable or returning a maintenance response.',
                ],
            );
        }
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
