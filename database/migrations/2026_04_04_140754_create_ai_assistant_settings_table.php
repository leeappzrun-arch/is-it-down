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
        Schema::create('ai_assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('settings_key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->string('provider_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('model')->default('gpt-4o-mini');
            $table->unsignedSmallInteger('request_timeout_seconds')->default(30);
            $table->text('system_prompt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_settings');
    }
};
