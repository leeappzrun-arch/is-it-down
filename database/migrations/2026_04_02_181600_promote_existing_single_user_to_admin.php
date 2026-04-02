<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (User::query()->count() !== 1) {
            return;
        }

        User::query()->first()?->update([
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (User::query()->count() !== 1) {
            return;
        }

        User::query()->first()?->update([
            'role' => User::ROLE_USER,
        ]);
    }
};
