<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_cashflows', function (Blueprint $table) {
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['account_id', 'updated_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_cashflows', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'updated_by_user_id']);
            $table->dropConstrainedForeignId('updated_by_user_id');
        });
    }
};
