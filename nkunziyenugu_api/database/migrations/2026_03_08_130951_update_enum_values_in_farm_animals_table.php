<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change ENUM values for 'sex'
        DB::statement("ALTER TABLE farm_animals 
            MODIFY sex ENUM('Male','Female','Unknown') NOT NULL DEFAULT 'Unknown'");

        // Change ENUM values for 'status'
        DB::statement("ALTER TABLE farm_animals 
            MODIFY status ENUM('Active','Sold','Dead') NOT NULL DEFAULT 'Active'");
    }

    public function down(): void
    {
        // Revert back to old lowercase enum values if needed
        DB::statement("ALTER TABLE farm_animals 
            MODIFY sex ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown'");

        DB::statement("ALTER TABLE farm_animals 
            MODIFY status ENUM('active','sold','dead') NOT NULL DEFAULT 'active'");
    }
};