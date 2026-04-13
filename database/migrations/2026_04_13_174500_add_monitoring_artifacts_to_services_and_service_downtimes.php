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
        Schema::table('services', function (Blueprint $table) {
            $table->json('last_response_headers')->nullable()->after('last_response_code');
            $table->string('last_screenshot_disk')->nullable()->after('last_response_headers');
            $table->string('last_screenshot_path')->nullable()->after('last_screenshot_disk');
            $table->timestamp('last_screenshot_captured_at')->nullable()->after('last_screenshot_path');
        });

        Schema::table('service_downtimes', function (Blueprint $table) {
            $table->json('started_response_headers')->nullable()->after('started_response_code');
            $table->json('latest_response_headers')->nullable()->after('latest_response_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_downtimes', function (Blueprint $table) {
            $table->dropColumn([
                'started_response_headers',
                'latest_response_headers',
            ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'last_response_headers',
                'last_screenshot_disk',
                'last_screenshot_path',
                'last_screenshot_captured_at',
            ]);
        });
    }
};
