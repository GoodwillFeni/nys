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
        Schema::table('farm_animals', function (Blueprint $table) {
            $table->foreignId('breed_id')
                ->nullable()
                ->after('animal_type_id')
                ->constrained('farm_animal_breeds')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_animals', function (Blueprint $table) {
            //
        });
    }
};
