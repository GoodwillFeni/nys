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
        Schema::create('farm_animal_device_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained('farm_animals')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->timestamp('linked_from');
            $table->timestamp('linked_to')->nullable();

            $table->timestamps();

            $table->index(['animal_id', 'linked_to']);
            $table->boolean('deleted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_animal_device_links');
    }
};
