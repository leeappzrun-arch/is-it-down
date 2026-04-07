<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Database\Seeder;

class ServiceTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $marketingSite = Service::query()
            ->with(['groups:id', 'recipientGroups:id', 'recipients:id'])
            ->where('name', 'Marketing Site')
            ->first();

        if ($marketingSite instanceof Service) {
            ServiceTemplate::query()->updateOrCreate(
                ['name' => 'Marketing site starter'],
                ServiceTemplateData::payloadFromService($marketingSite, 'Marketing site starter'),
            );
        }

        ServiceTemplate::query()->updateOrCreate(
            ['name' => 'Generic HTTP service'],
            [
                'configuration' => ServiceTemplateData::normalizeConfiguration([
                    'name' => 'Customer Portal',
                    'interval_seconds' => Service::INTERVAL_1_MINUTE,
                    'expect_type' => Service::EXPECT_TEXT,
                    'expect_value' => 'All systems operational',
                    'additional_headers' => [
                        ['name' => 'X-Monitor', 'value' => 'is-it-down'],
                    ],
                    'ssl_expiry_notifications_enabled' => true,
                    'service_group_ids' => [],
                    'recipient_group_ids' => [],
                    'recipient_ids' => [],
                ]),
            ],
        );
    }
}
