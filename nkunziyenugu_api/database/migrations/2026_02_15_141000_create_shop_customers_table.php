<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shop_customers');

        Schema::create('shop_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 191);
            $table->string('phone', 50);
            $table->string('email', 150)->nullable();
            $table->boolean('deleted')->default(false);

            $table->timestamps();

            $table->index(['account_id', 'phone']);
            $table->index(['account_id', 'name']);
            $table->index(['account_id', 'email']);
            $table->unique(['account_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_customers');
    }
};
