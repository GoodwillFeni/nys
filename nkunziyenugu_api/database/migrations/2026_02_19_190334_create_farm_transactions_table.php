<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farm_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained('farm_farms')->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained('farm_animals')->nullOnDelete();

            $table->enum('type', ['income', 'expense', 'loss']);
            $table->string('category');

            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');

            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->constrained('users');

            $table->timestamps();

            $table->index(['farm_id', 'type']);
            $table->boolean('deleted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_transactions');
    }
};
