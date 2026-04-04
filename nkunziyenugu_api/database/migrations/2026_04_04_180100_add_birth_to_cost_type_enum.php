<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `farm_animal_events` MODIFY `cost_type` ENUM('income','expense','loss','running','birth') NOT NULL DEFAULT 'expense'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `farm_animal_events` MODIFY `cost_type` ENUM('income','expense','loss','running') NOT NULL DEFAULT 'expense'");
    }
};
