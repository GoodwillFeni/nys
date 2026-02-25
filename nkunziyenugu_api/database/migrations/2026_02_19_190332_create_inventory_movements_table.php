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
        Schema::create('farm_inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('movement_type', ['purchase', 'issue', 'adjustment']);

            $table->decimal('qty', 14, 4);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();

            $table->date('movement_date');
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->constrained('users');

            $table->timestamps();

            $table->index(['inventory_item_id', 'movement_type']);
            $table->boolean('deleted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_inventory_movements');
    }
};
