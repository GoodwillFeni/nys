<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_pnl_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farm_farms')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->tinyInteger('month');

            // animal_events totals (cost_type buckets)
            $table->decimal('events_income',     14, 2)->default(0);
            $table->decimal('events_expense',    14, 2)->default(0);
            $table->decimal('events_running',    14, 2)->default(0);
            $table->decimal('events_loss',       14, 2)->default(0);
            $table->decimal('events_birth',      14, 2)->default(0);
            $table->decimal('events_investment', 14, 2)->default(0);

            // farm_transactions totals (type buckets)
            $table->decimal('tx_income',  14, 2)->default(0);
            $table->decimal('tx_expense', 14, 2)->default(0);
            $table->decimal('tx_loss',    14, 2)->default(0);

            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'farm_id', 'year', 'month'], 'pnl_monthly_unique');
            $table->index(['account_id', 'year', 'month'], 'pnl_monthly_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_pnl_monthly');
    }
};
