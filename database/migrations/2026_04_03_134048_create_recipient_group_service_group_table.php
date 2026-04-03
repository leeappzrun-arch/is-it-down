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
        Schema::create('recipient_group_service_group', function (Blueprint $table) {
            $table->foreignId('recipient_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_group_id')->constrained()->cascadeOnDelete();

            $table->primary(['recipient_group_id', 'service_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipient_group_service_group');
    }
};
