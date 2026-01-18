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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_uid')->unique(); // public ID
            $table->string('device_secret');        // private (hashed)
            $table->foreignId('account_id')
                  ->constrained('accounts');         // links to accounts.id
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('has_alarm')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('deleted_flag')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};