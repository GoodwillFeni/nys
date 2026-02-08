<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();

            $table->string('product_name', 255);
            $table->string('product_type', 100)->nullable();
            $table->text('description')->nullable();

            $table->integer('int_stock')->default(0);
            $table->integer('stock_level')->default(0);

            $table->decimal('stock_price', 12, 2)->default(0);
            $table->decimal('cal_price_no_prof', 12, 2)->default(0);
            $table->decimal('cal_price', 12, 2)->default(0);
            $table->decimal('actual_price', 12, 2)->default(0);
            $table->decimal('prof_per_product', 12, 2)->default(0);

            $table->string('img_path')->nullable();

            $table->boolean('deleted')->default(false);
            $table->timestamps();

            $table->index(['account_id', 'deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
