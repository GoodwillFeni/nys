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
    Schema::create('account_users', function (Blueprint $table) {
        $table->id();

        $table->foreignId('account_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('role', 50)->default('Member');
        $table->integer('deleted_flag')->default(0);

        $table->timestamps();

        $table->unique(['account_id', 'user_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_users');
    }
};
