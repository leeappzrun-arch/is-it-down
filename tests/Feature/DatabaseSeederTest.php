<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Recipient;
use App\Models\RecipientGroup;
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
        $this->assertDatabaseCount('api_keys', 3);

        $operationsInbox = Recipient::query()->where('name', 'Operations Inbox')->first();
        $operationsGroup = RecipientGroup::query()->where('name', 'Operations')->first();
        $leadershipGroup = RecipientGroup::query()->where('name', 'Leadership')->first();
        $serviceKey = ApiKey::query()->where('name', 'Status Page Worker Key')->first();

        $this->assertNotNull($operationsInbox);
        $this->assertNotNull($operationsGroup);
        $this->assertNotNull($leadershipGroup);
        $this->assertNotNull($serviceKey);
        $this->assertSame(
            [$leadershipGroup->id, $operationsGroup->id],
            $operationsInbox->groups()->orderBy('recipient_groups.name')->pluck('recipient_groups.id')->all(),
        );
        $this->assertNull($serviceKey->user_id);
        $this->assertSame(ApiKey::OWNER_SERVICE, $serviceKey->owner_type);
        $this->assertSame(
            [
                ApiKeyPermissions::permission('recipients', 'read'),
                ApiKeyPermissions::permission('recipients', 'write'),
            ],
            $serviceKey->permissions,
        );
    }
}
