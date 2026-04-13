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
        Schema::table('recipients', function (Blueprint $table) {
            $table->json('additional_headers')->nullable()->after('webhook_auth_header_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn('additional_headers');
        });
    }
};
