<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds idempotency_key to shop_pos_sales so that a POS double-click (or a
 * dropped network request that the client retries) cannot create a duplicate
 * sale. The frontend generates a UUID per cart and sends it on every
 * checkout attempt; the unique constraint on (account_id, idempotency_key)
 * lets the backend short-circuit duplicate writes by returning the existing
 * sale instead of creating a new one.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('shop_pos_sales', function (Blueprint $t) {
            $t->uuid('idempotency_key')->nullable()->after('id');
            $t->unique(['account_id', 'idempotency_key'], 'shop_pos_sales_idem_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shop_pos_sales', function (Blueprint $t) {
            $t->dropUnique('shop_pos_sales_idem_unique');
            $t->dropColumn('idempotency_key');
        });
    }
};
