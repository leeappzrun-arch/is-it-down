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
            $table->string('current_status')->nullable()->after('expect_value');
            $table->unsignedSmallInteger('last_response_code')->nullable()->after('current_status');
            $table->text('last_check_reason')->nullable()->after('last_response_code');
            $table->timestamp('last_checked_at')->nullable()->after('last_check_reason');
            $table->timestamp('next_check_at')->nullable()->after('last_checked_at');
            $table->timestamp('last_status_changed_at')->nullable()->after('next_check_at');

            $table->index('current_status');
            $table->index('next_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['current_status']);
            $table->dropIndex(['next_check_at']);
            $table->dropColumn([
                'current_status',
                'last_response_code',
                'last_check_reason',
                'last_checked_at',
                'next_check_at',
                'last_status_changed_at',
            ]);
        });
    }
};
