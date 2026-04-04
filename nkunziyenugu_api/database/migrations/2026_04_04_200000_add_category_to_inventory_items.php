<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_inventory_items', function (Blueprint $table) {
            $table->enum('category', ['feed', 'vaccine', 'medicine', 'supplement', 'equipment', 'other'])
                  ->default('other')
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('farm_inventory_items', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
