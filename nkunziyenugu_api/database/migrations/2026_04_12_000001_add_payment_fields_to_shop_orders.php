<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('payment_method', 50)->nullable()->after('notes');   // pay_in_store | deposit | credit
            $table->string('payment_proof_path')->nullable()->after('payment_method'); // uploaded deposit slip
            $table->foreignId('customer_id')->nullable()->constrained('shop_customers')->nullOnDelete()->after('user_id');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('customer_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->timestamp('paid_at')->nullable()->after('rejection_reason'); // credit orders: when payment received
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['payment_method', 'payment_proof_path', 'approved_at', 'rejection_reason', 'paid_at']);
        });
    }
};
