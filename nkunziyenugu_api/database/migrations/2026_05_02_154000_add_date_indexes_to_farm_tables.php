<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds date-aware indexes to farm_transactions for the P&L scaling work.
 * (farm_animal_events already has [farm_id, event_date] from the original
 *  migration, so no index is needed there.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_transactions', function (Blueprint $table) {
            $table->index('transaction_date', 'tx_date_idx');
            $table->index(['account_id', 'transaction_date'], 'tx_account_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('farm_transactions', function (Blueprint $table) {
            $table->dropIndex('tx_date_idx');
            $table->dropIndex('tx_account_date_idx');
        });
    }
};
