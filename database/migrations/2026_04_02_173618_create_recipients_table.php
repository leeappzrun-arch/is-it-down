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
        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint');
            $table->string('webhook_auth_type')->default('none');
            $table->string('webhook_auth_username')->nullable();
            $table->text('webhook_auth_password')->nullable();
            $table->text('webhook_auth_token')->nullable();
            $table->string('webhook_auth_header_name')->nullable();
            $table->text('webhook_auth_header_value')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
