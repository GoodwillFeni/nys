<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds credit_limit + credit_used to shop_customers.
 *
 * Until now there was no ceiling — customers could rack up unlimited Credit
 * sales. Now:
 *   - credit_limit = ceiling set per customer (0 = no credit allowed; default)
 *   - credit_used  = running balance, bumped on Credit sale, decremented on payoff
 * The POS checkout refuses any Credit sale that would push (credit_used + sale)
 * over credit_limit.
 *
 * decimal(12,2) matches the rest of the money columns on shop_pos_sales.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('shop_customers', function (Blueprint $t) {
            $t->decimal('credit_limit', 12, 2)->default(0)->after('email');
            $t->decimal('credit_used',  12, 2)->default(0)->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('shop_customers', function (Blueprint $t) {
            $t->dropColumn(['credit_limit', 'credit_used']);
        });
    }
};
