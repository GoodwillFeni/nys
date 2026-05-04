<?php

/**
 * Defence-in-depth smoke test — Chunk 4 of the SaaS-readiness plan.
 *
 * Run via: php artisan tinker --execute="require 'tests/smoke/defence-in-depth.php';"
 *
 * Verifies:
 *  - current_account_id() helper is autoloaded
 *  - BelongsToAccount trait applied to all expected models, no-op in CLI ctx
 *  - SoftDeletesViaFlag trait works on FarmAnimalEvent (previously broken)
 *  - is_owner column exists + at least one row marked
 *  - FarmReportController has inline canDo() check
 *  - hasPrivilegedRole helper + all callers fully removed
 *  - Permission const maps generated for Vue + RN
 */

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\FarmAnimalEvent;
use Illuminate\Support\Facades\Schema;

echo "\n=== DEFENCE IN DEPTH SMOKE TEST ===\n\n";

$pass = 0;
$fail = 0;
$report = function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail) {
    $tag = $ok ? "[PASS]" : "[FAIL]";
    echo "$tag $name" . ($detail ? "  — $detail" : '') . "\n";
    $ok ? $pass++ : $fail++;
};

// ── 1. Helper autoloaded ──────────────────────────────────────────
$report('current_account_id() helper is autoloaded', function_exists('current_account_id'));
$report('current_account_id() returns null in CLI', current_account_id() === null);

// ── 2. BelongsToAccount applied to each domain model ──────────────
$shopModels = [
    \App\Models\ShopProduct::class,
    \App\Models\ShopOrder::class,
    \App\Models\ShopCustomer::class,
    \App\Models\ShopPosSale::class,
    \App\Models\ShopCashflow::class,
    \App\Models\ShopCreditRequest::class,
    \App\Models\ShopPosCart::class,
];
$farmModels = [
    \App\Models\FarmAnimal::class,
    \App\Models\Farm::class,
    \App\Models\FarmAnimalEvent::class,
    \App\Models\FarmTransaction::class,
    \App\Models\InventoryItem::class,
    \App\Models\InventoryMovement::class,
    \App\Models\AnimalBreed::class,
    \App\Models\Device::class,
    \App\Models\AnimalDeviceLink::class,
    \App\Models\FarmPnlMonthly::class,
];
foreach (array_merge($shopModels, $farmModels) as $m) {
    $uses = class_uses_recursive($m);
    $report("$m uses BelongsToAccount", in_array(\App\Traits\BelongsToAccount::class, $uses, true));
}

// ── 3. CLI queries still work (trait is no-op) ────────────────────
$report('CLI: ShopProduct::count() works', \App\Models\ShopProduct::count() >= 0);
$report('CLI: FarmAnimal::count() works', \App\Models\FarmAnimal::count() >= 0);

// ── 4. SoftDeletesViaFlag trait usable on FarmAnimalEvent ─────────
$ev = FarmAnimalEvent::orderByDesc('id')->first();
if ($ev) {
    $orig = $ev->deleted;
    $ok1 = $ev->softDelete() && (FarmAnimalEvent::find($ev->id)->deleted == 1);
    $ok2 = $ev->restoreFromDelete() && (FarmAnimalEvent::find($ev->id)->deleted == 0);
    \DB::table('farm_animal_events')->where('id', $ev->id)->update(['deleted' => $orig]);
    $report('FarmAnimalEvent::softDelete() round-trips', $ok1 && $ok2);
} else {
    echo "[SKIP] No farm_animal_events to test soft-delete round-trip.\n";
}

// ── 5. is_owner column + backfill ─────────────────────────────────
$report('account_users.is_owner column exists', Schema::hasColumn('account_users', 'is_owner'));
$ownerCount = \DB::table('account_users')->where('is_owner', 1)->count();
$report('At least one account_user marked is_owner=1', $ownerCount >= 1, "owners=$ownerCount");

// ── 6. AccountUser model has is_owner in fillable ────────────────
$au = new AccountUser();
$report('AccountUser fillable includes is_owner', in_array('is_owner', $au->getFillable(), true));

// ── 7. FarmReportController inline canDo check ────────────────────
$ctrl = file_get_contents(base_path('app/Http/Controllers/Api/FarmReportController.php'));
$report(
    'FarmReportController::pnl has inline canDo() check',
    str_contains($ctrl, "canDo('PnlReport', 'view'") ||
    str_contains($ctrl, '$user->canDo(\'PnlReport\'')
);
$report(
    'FarmReportController no longer reads account_id from request body',
    !preg_match('/\$request->account_id\s*\?\?\s*\$request->header/', $ctrl)
);

// ── 8. hasPrivilegedRole completely removed ───────────────────────
$shopBase = file_get_contents(base_path('app/Http/Controllers/Api/ShopBaseController.php'));
$report('hasPrivilegedRole helper removed from ShopBaseController', !str_contains($shopBase, 'function hasPrivilegedRole'));

$shopFiles = glob(base_path('app/Http/Controllers/Api/Shop*Controller.php'));
$callerHits = 0;
foreach ($shopFiles as $f) {
    $body = file_get_contents($f);
    $callerHits += substr_count($body, '$this->hasPrivilegedRole(');
}
$report('No remaining $this->hasPrivilegedRole() callers in shop controllers', $callerHits === 0, "hits=$callerHits");

// ── 9. Permission const maps generated ────────────────────────────
$webConst = base_path('../nkunziyenugu_systems/src/constants/permissions.js');
$appConst = base_path('../nys_app/src/constants/permissions.ts');
$report('Vue permissions.js generated', file_exists($webConst));
$report('RN permissions.ts generated', file_exists($appConst));

if (file_exists($webConst)) {
    $body = file_get_contents($webConst);
    $report('Vue permissions.js exports P (route names)',  str_contains($body, 'export const P = Object.freeze'));
    $report('Vue permissions.js exports A (action names)', str_contains($body, 'export const A = Object.freeze'));
    $report('Vue permissions.js includes ShopDashboard',   str_contains($body, "ShopDashboard: 'ShopDashboard'"));
}
if (file_exists($appConst)) {
    $body = file_get_contents($appConst);
    $report('RN permissions.ts exports P with as const',  str_contains($body, 'export const P = {'));
    $report('RN permissions.ts has type RouteName',       str_contains($body, 'export type RouteName'));
}

echo "\n=== RESULT: $pass passed, $fail failed ===\n";
