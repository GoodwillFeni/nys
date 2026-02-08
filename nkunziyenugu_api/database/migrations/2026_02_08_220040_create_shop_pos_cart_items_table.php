<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_pos_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_cart_id')->constrained('shop_pos_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();

            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('prof_per_product', 12, 2)->default(0);
            $table->integer('pre_stock_level')->default(0);

            $table->timestamps();

            $table->unique(['pos_cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_pos_cart_items');
    }
};
