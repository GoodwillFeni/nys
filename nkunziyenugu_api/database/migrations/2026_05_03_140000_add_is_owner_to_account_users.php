<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds is_owner boolean to account_users.
 *
 * Reserved for the user who created the account (or the user a previous
 * owner transferred ownership to). Used ONLY for billing-tier actions
 * (delete the account, change owner, change subscription). Day-to-day
 * permissions continue to flow through route_access / action_access JSON.
 *
 * Backfill: every existing row currently has the Owner preset (full * / *),
 * so historically there's no way to know who the "real" owner was.
 * Best-effort: mark the OLDEST account_user row per account as owner,
 * using created_at then id as the tiebreaker.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('account_users', function (Blueprint $t) {
            $t->boolean('is_owner')->default(false)->after('action_access');
        });

        // Mark the earliest (account_id, user_id) pivot row per account
        // as owner. For Postgres / SQLite this would need a different
        // dialect; for our MySQL setup the subquery is fine.
        DB::statement("
            UPDATE account_users au
            JOIN (
                SELECT account_id, MIN(id) AS first_id
                FROM account_users
                WHERE deleted_flag = 0
                GROUP BY account_id
            ) first
              ON first.account_id = au.account_id
             AND first.first_id   = au.id
            SET au.is_owner = 1
        ");
    }

    public function down(): void
    {
        Schema::table('account_users', function (Blueprint $t) {
            $t->dropColumn('is_owner');
        });
    }
};
