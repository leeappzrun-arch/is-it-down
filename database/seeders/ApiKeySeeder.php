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
                'user_id' => $owner->id,
            ],
            [
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
