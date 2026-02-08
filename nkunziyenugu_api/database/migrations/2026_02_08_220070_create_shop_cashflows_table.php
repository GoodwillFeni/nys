<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_cashflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('transaction_type', 100);
            $table->string('payment_type', 100)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('date');
            $table->text('notes')->nullable();

            $table->boolean('deleted')->default(false);
            $table->timestamps();

            $table->index(['account_id', 'date', 'deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_cashflows');
    }
};
