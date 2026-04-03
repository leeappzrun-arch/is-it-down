<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\User;
use App\Support\ApiKeyPermissions;
use DateTimeInterface;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $user = User::query()->where('email', 'user@example.com')->firstOrFail();

        $this->seedUserOwnedKey(
            name: 'Admin Console Key',
            owner: $admin,
            creator: $admin,
            permissions: ApiKeyPermissions::all(),
            plainTextToken: 'iid_seed_admin_console_key_000001',
            expiresAt: now()->addYear(),
        );

        $this->seedUserOwnedKey(
            name: 'User Read Only Key',
            owner: $user,
            creator: $admin,
            permissions: ApiKeyPermissions::normalize([
                ApiKeyPermissions::permission('recipients', 'read'),
            ]),
            plainTextToken: 'iid_seed_user_read_key_000002',
            expiresAt: now()->addMonths(6),
        );

        $this->seedServiceKey(
            name: 'Status Page Worker Key',
            creator: $admin,
            serviceName: 'Status page worker',
            permissions: ApiKeyPermissions::normalize([
                ApiKeyPermissions::permission('recipients', 'read'),
                ApiKeyPermissions::permission('recipients', 'write'),
            ]),
            plainTextToken: 'iid_seed_status_page_worker_000003',
        );
    }

    /**
     * Create or update a user-owned API key.
     *
     * @param  array<int, string>  $permissions
     */
    private function seedUserOwnedKey(
        string $name,
        User $owner,
        User $creator,
        array $permissions,
        string $plainTextToken,
        ?DateTimeInterface $expiresAt = null,
    ): void {
        ApiKey::query()->updateOrCreate(
            [
                'name' => $name,
                'owner_type' => ApiKey::OWNER_USER,
                'user_id' => $owner->id,
            ],
            [
                'service_name' => null,
                'created_by_id' => $creator->id,
                'token_prefix' => substr($plainTextToken, 0, 12),
                'token_hash' => ApiKey::hashToken($plainTextToken),
                'permissions' => $permissions,
                'expires_at' => $expiresAt,
                'last_used_at' => null,
                'revoked_at' => null,
            ],
        );
    }

    /**
     * Create or update a service API key.
     *
     * @param  array<int, string>  $permissions
     */
    private function seedServiceKey(
        string $name,
        User $creator,
        string $serviceName,
        array $permissions,
        string $plainTextToken,
        ?DateTimeInterface $expiresAt = null,
    ): void {
        ApiKey::query()->updateOrCreate(
            [
                'name' => $name,
                'owner_type' => ApiKey::OWNER_SERVICE,
                'service_name' => $serviceName,
            ],
            [
                'user_id' => null,
                'created_by_id' => $creator->id,
                'token_prefix' => substr($plainTextToken, 0, 12),
                'token_hash' => ApiKey::hashToken($plainTextToken),
                'permissions' => $permissions,
                'expires_at' => $expiresAt,
                'last_used_at' => null,
                'revoked_at' => null,
            ],
        );
    }
}
