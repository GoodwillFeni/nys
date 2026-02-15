<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_pos_sales', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('cashier_user_id')
                ->constrained('shop_customers')
                ->nullOnDelete();

            $table->string('customer_phone', 50)->nullable()->after('customer_name');

            $table->index(['account_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_pos_sales', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'customer_id']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['customer_phone']);
        });
    }
};
