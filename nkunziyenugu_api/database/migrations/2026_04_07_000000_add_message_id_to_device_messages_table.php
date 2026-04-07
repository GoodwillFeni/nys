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
        Schema::table('device_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('device_messages', 'message_id')) {
                $table->bigInteger('message_id')->nullable()->after('device_id');
                $table->unique(['device_id', 'message_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_messages', function (Blueprint $table) {
            if (Schema::hasColumn('device_messages', 'message_id')) {
                $table->dropColumn('message_id');
            }
        });
    }
};
