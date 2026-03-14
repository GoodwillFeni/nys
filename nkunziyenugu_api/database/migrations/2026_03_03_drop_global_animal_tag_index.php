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
            // drop the global unique constraint on animal_tag if present
            if (Schema::hasColumn('farm_animals','animal_tag')) {
                $indexExists = DB::select(
                    "SHOW INDEX FROM `farm_animals` WHERE Key_name = ?",
                    ['farm_animals_account_id_global_tag_unique']
                );
                if (count($indexExists)) {
                    $table->dropUnique('farm_animals_account_id_global_tag_unique');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('farm_animals', function (Blueprint $table) {
            $table->unique(['account_id','animal_tag'], 'farm_animals_account_id_global_tag_unique');
        });
    }
};
