<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_animals', function (Blueprint $table) {
            // Drop old constraint: unique per account
            $indexes = DB::select("SHOW INDEX FROM `farm_animals` WHERE Key_name = 'farm_animals_account_id_animal_tag_unique'");
            if (count($indexes)) {
                $table->dropUnique('farm_animals_account_id_animal_tag_unique');
            }

            // Add new constraint: unique per farm
            $table->unique(['farm_id', 'animal_tag'], 'farm_animals_farm_id_animal_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::table('farm_animals', function (Blueprint $table) {
            $table->dropUnique('farm_animals_farm_id_animal_tag_unique');
            $table->unique(['account_id', 'animal_tag'], 'farm_animals_account_id_animal_tag_unique');
        });
    }
};
