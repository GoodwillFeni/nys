<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_pos_sales', function (Blueprint $table) {
            $table->string('payment_method', 30)->default('Cash')->after('customer_name');
            $table->decimal('amount_received', 12, 2)->nullable()->after('payment_method');
            $table->decimal('change_amount', 12, 2)->nullable()->after('amount_received');

            $table->boolean('is_paid')->default(true)->after('sale_datetime');
            $table->timestamp('paid_at')->nullable()->after('is_paid');
            $table->string('paid_method', 30)->nullable()->after('paid_at');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('paid_method');

            $table->index(['account_id', 'payment_method', 'is_paid']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_pos_sales', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'payment_method', 'is_paid']);
            $table->dropColumn([
                'payment_method',
                'amount_received',
                'change_amount',
                'is_paid',
                'paid_at',
                'paid_method',
                'paid_amount',
            ]);
        });
    }
};
