<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `account_users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'Member'");
    }

    public function down(): void
    {
        // no-op
    }
};
