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
        Schema::create('farm_animal_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farm_farms')->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained('farm_animals')->cascadeOnDelete();

            $table->string('event_type');
            $table->date('event_date');

            $table->json('meta')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');

            $table->timestamps();

            $table->index(['animal_id', 'event_type']);
            $table->boolean('deleted')->default(false);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_animal_events');
    }
};
