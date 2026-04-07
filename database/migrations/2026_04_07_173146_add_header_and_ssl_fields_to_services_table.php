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
            $table->json('additional_headers')->nullable()->after('expect_value');
            $table->boolean('ssl_expiry_notifications_enabled')->default(false)->after('additional_headers');
            $table->timestamp('last_ssl_expiry_notification_sent_at')->nullable()->after('last_status_changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'additional_headers',
                'ssl_expiry_notifications_enabled',
                'last_ssl_expiry_notification_sent_at',
            ]);
        });
    }
};
