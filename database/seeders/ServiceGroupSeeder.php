<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recipientGroups = RecipientGroup::query()
            ->whereIn('name', ['Operations', 'Leadership', 'Vendors'])
            ->get()
            ->keyBy('name');

        $recipients = Recipient::query()
            ->whereIn('name', ['Operations Inbox', 'PagerDuty Webhook', 'Vendor Status Webhook'])
            ->get()
            ->keyBy('name');

        $this->seedServiceGroup(
            name: 'Production',
            recipientIds: [
                $recipients->get('PagerDuty Webhook')?->id,
            ],
            recipientGroupIds: [
                $recipientGroups->get('Operations')?->id,
            ],
        );

        $this->seedServiceGroup(
            name: 'Stakeholders',
            recipientIds: [
                $recipients->get('Operations Inbox')?->id,
            ],
            recipientGroupIds: [
                $recipientGroups->get('Leadership')?->id,
            ],
        );
    }

    /**
     * Create or update a service group and align its assignments.
     *
     * @param  array<int, int|null>  $recipientIds
     * @param  array<int, int|null>  $recipientGroupIds
     */
    private function seedServiceGroup(string $name, array $recipientIds, array $recipientGroupIds): void
    {
        $serviceGroup = ServiceGroup::query()->updateOrCreate(
            ['name' => $name],
            [],
        );

        $serviceGroup->recipients()->sync(array_values(array_filter($recipientIds)));
        $serviceGroup->recipientGroups()->sync(array_values(array_filter($recipientGroupIds)));
    }
}
