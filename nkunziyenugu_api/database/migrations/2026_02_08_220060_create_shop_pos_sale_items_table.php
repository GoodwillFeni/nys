<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('shop_pos_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();

            $table->string('product_name', 255);
            $table->string('product_type', 100)->nullable();
            $table->integer('qty_sold')->default(1);
            $table->decimal('actual_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('prof_per_product', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['pos_sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_pos_sale_items');
    }
};
