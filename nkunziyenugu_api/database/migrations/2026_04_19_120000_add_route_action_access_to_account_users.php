<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 2 for the permissions refactor: add the new JSON columns and
 * backfill them from the existing `role` + `can_manage_devices` values.
 * Does NOT drop old columns yet — that happens in a follow-up migration
 * after the code has been updated to read the new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_users', function (Blueprint $table) {
            $table->json('route_access')->nullable()->after('user_id');
            $table->json('action_access')->nullable()->after('route_access');
        });

        $presets   = require base_path('config/permissions_presets.php');
        $registry  = require base_path('config/permissions_registry.php');
        $allRoutes  = array_map(fn($r) => $r['name'], $registry['routes']);
        $allActions = array_map(fn($a) => $a['name'], $registry['actions']);

        $expand = function ($value, array $all) {
            if ($value === '*') return $all;
            return is_array($value) ? $value : [];
        };

        $resolve = function (?string $role) use ($presets, $allRoutes, $allActions, $expand) {
            $key = null;
            if ($role) {
                foreach (array_keys($presets) as $preset) {
                    if (strtolower($preset) === strtolower($role)) { $key = $preset; break; }
                }
            }
            $preset = $presets[$key] ?? $presets['Viewer'];
            return [
                'routes'  => $expand($preset['routes'], $allRoutes),
                'actions' => $expand($preset['actions'], $allActions),
            ];
        };

        $rows = DB::table('account_users')->get();
        foreach ($rows as $row) {
            $resolved = $resolve($row->role ?? null);
            $routes = $resolved['routes'];
            $actions = $resolved['actions'];

            if (!empty($row->can_manage_devices)) {
                if (!in_array('DeviceConfig', $routes, true)) $routes[] = 'DeviceConfig';
                if (!in_array('DevicesList', $routes, true))  $routes[] = 'DevicesList';
                if (!in_array('AddDevice', $routes, true))    $routes[] = 'AddDevice';
                if (!in_array('configure', $actions, true))   $actions[] = 'configure';
            }

            DB::table('account_users')
                ->where('id', $row->id)
                ->update([
                    'route_access'  => json_encode(array_values(array_unique($routes))),
                    'action_access' => json_encode(array_values(array_unique($actions))),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('account_users', function (Blueprint $table) {
            $table->dropColumn(['route_access', 'action_access']);
        });
    }
};
