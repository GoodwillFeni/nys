<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_animal_events', function (Blueprint $table) {

            // 🔥 Drop foreign key FIRST
            $table->dropForeign(['created_by_user_id']);

            // Then drop column
            $table->dropColumn('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_animal_events', function (Blueprint $table) {

            $table->foreignId('created_by_user_id')
                  ->constrained('users');
        });
    }
};