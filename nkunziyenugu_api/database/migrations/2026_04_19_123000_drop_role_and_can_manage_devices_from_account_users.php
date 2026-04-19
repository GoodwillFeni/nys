<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2 of 2 for the permissions refactor: drop the now-unused `role` and
 * `can_manage_devices` columns after code has been updated to read from
 * `route_access` + `action_access`.
 *
 * Run this only AFTER all controllers / models / frontend code have been
 * updated and deployed. The previous migration (2026_04_19_120000) has
 * already backfilled the new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_users', function (Blueprint $table) {
            if (Schema::hasColumn('account_users', 'can_manage_devices')) {
                $table->dropColumn('can_manage_devices');
            }
            if (Schema::hasColumn('account_users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }

    /**
     * Best-effort restore: look up each row's route_access against the presets
     * to recover a role label, and recover can_manage_devices by checking for
     * DeviceConfig + configure.
     */
    public function down(): void
    {
        Schema::table('account_users', function (Blueprint $table) {
            $table->string('role', 50)->default('Member')->after('user_id');
            $table->boolean('can_manage_devices')->default(false)->after('role');
        });

        $presets   = require base_path('config/permissions_presets.php');
        $registry  = require base_path('config/permissions_registry.php');
        $allRoutes  = array_map(fn($r) => $r['name'], $registry['routes']);
        $allActions = array_map(fn($a) => $a['name'], $registry['actions']);

        $expand = function ($value, array $all) {
            if ($value === '*') return $all;
            return is_array($value) ? $value : [];
        };

        $rows = DB::table('account_users')->get();
        foreach ($rows as $row) {
            $rowRoutes  = json_decode($row->route_access ?? '[]', true) ?: [];
            $rowActions = json_decode($row->action_access ?? '[]', true) ?: [];
            sort($rowRoutes);
            sort($rowActions);

            $match = 'Viewer';
            foreach ($presets as $name => $preset) {
                $presetRoutes  = $expand($preset['routes'], $allRoutes);
                $presetActions = $expand($preset['actions'], $allActions);
                sort($presetRoutes);
                sort($presetActions);
                if ($rowRoutes === $presetRoutes && $rowActions === $presetActions) {
                    $match = $name;
                    break;
                }
            }

            $canManage = in_array('DeviceConfig', $rowRoutes, true) && in_array('configure', $rowActions, true);

            DB::table('account_users')
                ->where('id', $row->id)
                ->update([
                    'role' => $match,
                    'can_manage_devices' => $canManage ? 1 : 0,
                ]);
        }
    }
};
