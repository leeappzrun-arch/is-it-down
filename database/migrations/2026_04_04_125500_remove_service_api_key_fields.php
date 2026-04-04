<?php

use App\Models\ApiKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ApiKey::query()
            ->whereNull('user_id')
            ->delete();

        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropIndex('api_keys_owner_type_user_id_index');
            $table->dropColumn(['owner_type', 'service_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->string('owner_type')->default('user')->after('name');
            $table->string('service_name')->nullable()->after('user_id');
            $table->index(['owner_type', 'user_id']);
        });
    }
};
