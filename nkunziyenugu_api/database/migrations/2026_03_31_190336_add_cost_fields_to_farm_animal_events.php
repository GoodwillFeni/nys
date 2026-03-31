<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_animal_events', function (Blueprint $table) {

            $table->decimal('cost', 10, 2)->default(0)->after('event_date');

            $table->enum('cost_type', ['income', 'expense', 'loss', 'running'])
                  ->default('expense')
                  ->after('cost');

            $table->string('batch_id')->nullable()->after('cost_type');

            // Indexes for performance
            $table->index(['farm_id', 'event_date']);
            $table->index(['cost_type']);
        });
    }

    public function down(): void
    {
        Schema::table('farm_animal_events', function (Blueprint $table) {

            $table->dropColumn(['cost', 'cost_type', 'batch_id']);

            $table->dropIndex(['farm_id', 'event_date']);
            $table->dropIndex(['cost_type']);
        });
    }
};