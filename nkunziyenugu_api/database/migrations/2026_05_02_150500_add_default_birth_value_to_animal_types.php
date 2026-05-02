<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_animal_types', function (Blueprint $table) {
            $table->decimal('default_birth_value', 10, 2)->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('farm_animal_types', function (Blueprint $table) {
            $table->dropColumn('default_birth_value');
        });
    }
};
