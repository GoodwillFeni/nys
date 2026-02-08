<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('customer_name', 255)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_profit', 12, 2)->default(0);
            $table->timestamp('sale_datetime')->useCurrent();

            $table->timestamps();

            $table->index(['account_id', 'sale_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_pos_sales');
    }
};
