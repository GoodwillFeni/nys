<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_pos_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('customer_name', 255)->nullable();
            $table->string('status', 50)->default('open');

            $table->timestamps();

            $table->index(['account_id', 'cashier_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_pos_carts');
    }
};
