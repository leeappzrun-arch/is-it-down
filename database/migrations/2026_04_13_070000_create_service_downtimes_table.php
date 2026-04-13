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
        Schema::create('service_downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('started_reason')->nullable();
            $table->text('latest_reason')->nullable();
            $table->text('recovery_reason')->nullable();
            $table->unsignedSmallInteger('started_response_code')->nullable();
            $table->unsignedSmallInteger('latest_response_code')->nullable();
            $table->unsignedSmallInteger('recovery_response_code')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedTinyInteger('last_check_attempts')->default(1);
            $table->string('screenshot_disk')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->timestamp('screenshot_captured_at')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamp('ai_summary_created_at')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'started_at']);
            $table->index(['service_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_downtimes');
    }
};
