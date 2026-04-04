<?php

namespace Tests\Feature;

use App\Models\AiAssistantSetting;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\User;
use App\Support\ApiKeyPermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_default_users_and_related_management_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->first();
        $user = User::query()->where('email', 'user@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($user);
        $this->assertTrue($admin->isAdmin());
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('recipient_groups', 3);
        $this->assertDatabaseCount('recipients', 3);
        $this->assertDatabaseCount('service_groups', 2);
        $this->assertDatabaseCount('services', 2);
        $this->assertDatabaseCount('api_keys', 2);
        $this->assertDatabaseCount('ai_assistant_settings', 1);

        $operationsInbox = Recipient::query()->where('name', 'Operations Inbox')->first();
        $operationsGroup = RecipientGroup::query()->where('name', 'Operations')->first();
        $leadershipGroup = RecipientGroup::query()->where('name', 'Leadership')->first();
        $productionGroup = ServiceGroup::query()->where('name', 'Production')->first();
        $marketingSite = Service::query()->where('name', 'Marketing Site')->first();
        $this->assertNotNull($operationsInbox);
        $this->assertNotNull($operationsGroup);
        $this->assertNotNull($leadershipGroup);
        $this->assertNotNull($productionGroup);
        $this->assertNotNull($marketingSite);
        $this->assertSame(
            [$leadershipGroup->id, $operationsGroup->id],
            $operationsInbox->groups()->orderBy('recipient_groups.name')->pluck('recipient_groups.id')->all(),
        );
        $this->assertSame([$productionGroup->id], $marketingSite->groups()->where('service_groups.name', 'Production')->pluck('service_groups.id')->all());
        $this->assertSame(Service::EXPECT_TEXT, $marketingSite->expect_type);
        $this->assertFalse(AiAssistantSetting::current()->is_enabled);
        $this->assertContains(ApiKeyPermissions::permission('services', 'read'), ApiKeyPermissions::all());
        $this->assertContains(ApiKeyPermissions::permission('services', 'write'), ApiKeyPermissions::all());
    }
}
