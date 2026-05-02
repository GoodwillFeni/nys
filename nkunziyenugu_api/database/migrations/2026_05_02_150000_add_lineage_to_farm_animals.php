<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_animals', function (Blueprint $table) {
            $table->foreignId('mother_id')->nullable()->after('breed_id')
                  ->constrained('farm_animals')->nullOnDelete();
            $table->index('mother_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_animals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mother_id');
        });
    }
};
