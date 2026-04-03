<?php

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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_type');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_prefix', 12);
            $table->string('token_hash', 64)->unique();
            $table->json('permissions');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
