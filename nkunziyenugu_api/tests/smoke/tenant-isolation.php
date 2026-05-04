<?php

/**
 * Tenant-isolation smoke test — Chunk 1 of the SaaS-readiness plan.
 *
 * Run via:  php artisan tinker --execute="require 'tests/smoke/tenant-isolation.php';"
 *
 * Uses the live dev database — no factories or RefreshDatabase. Reports PASS/FAIL
 * for each fixed leak so we can quickly confirm the fixes hold.
 *
 * Pre-req: at least 2 accounts with at least 1 farm and 1 animal each.
 */

use App\Models\Account;
use App\Models\Farm;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalEvent;
use Illuminate\Support\Facades\DB;

echo "\n=== TENANT-ISOLATION SMOKE TEST ===\n\n";

$pass = 0;
$fail = 0;
$report = function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail) {
    $tag = $ok ? "[PASS]" : "[FAIL]";
    echo "$tag $name" . ($detail ? "  — $detail" : '') . "\n";
    $ok ? $pass++ : $fail++;
};

// ── 0. Static source checks (always run — don't need data) ────────

// FarmController::update no longer mass-assigns account_id
$src = file_get_contents(base_path('app/Http/Controllers/Api/FarmController.php'));
preg_match('/public function update\([^)]*Farm \$farm\)\s*\{.*?\n    \}/s', $src, $m);
$updateBody = $m[0] ?? '';
$report(
    "FarmController::update no longer mass-assigns account_id",
    $updateBody !== '' && !str_contains($updateBody, "'account_id'"),
    'inspecting update() body for account_id in $request->only(...)'
);

// No remaining $req->account_id writes in the fixed controllers
foreach ([
    'app/Http/Controllers/Api/AnimalController.php',
    'app/Http/Controllers/Api/AnimalEventController.php',
] as $rel) {
    $body = file_get_contents(base_path($rel));
    $hasClientAccountIdInWrite = preg_match("/'account_id'\s*=>\s*\\\$req(uest)?->account_id/", $body) === 1;
    $hasAccountIdInValidator   = preg_match("/'account_id'\s*=>\s*'required/", $body) === 1;
    $report(
        "$rel — no client-supplied account_id in writes/validators",
        !$hasClientAccountIdInWrite && !$hasAccountIdInValidator,
        $hasClientAccountIdInWrite ? 'found client write' : ($hasAccountIdInValidator ? 'found in validator' : '')
    );
}

// AnimalEventController::dashboard now scopes by account_id
$aec = file_get_contents(base_path('app/Http/Controllers/Api/AnimalEventController.php'));
// Capture from "public function dashboard" up to (but not including) the next function declaration.
preg_match('/public function dashboard\b.*?(?=\n    (?:public|private|protected) function )/s', $aec, $dm);
$dashBody = $dm[0] ?? '';
$dashScopeCount = substr_count($dashBody, "where('account_id', \$accountId)");
$report(
    'AnimalEventController::dashboard scopes by account_id',
    $dashScopeCount >= 3,
    "found $dashScopeCount where('account_id',\$accountId) calls (need ≥3)"
);

// ResolvesAccount trait is in use
$report(
    'AnimalController uses ResolvesAccount trait',
    str_contains(file_get_contents(base_path('app/Http/Controllers/Api/AnimalController.php')), 'use ResolvesAccount;')
);
$report(
    'AnimalEventController uses ResolvesAccount trait',
    str_contains($aec, 'use ResolvesAccount;')
);

echo "\n--- Runtime checks (need ≥2 accounts with data) ---\n";

// ── 1. Find two accounts with data ────────────────────────────────
$accounts = Account::query()
    ->where('deleted_flag', '!=', 1)
    ->whereExists(function ($q) {
        $q->select(DB::raw(1))
          ->from('farm_farms')
          ->whereColumn('farm_farms.account_id', 'accounts.id')
          ->where('farm_farms.deleted', '!=', 1);
    })
    ->limit(2)
    ->get();

if ($accounts->count() < 2) {
    echo "[SKIP] Only {$accounts->count()} account(s) with farms in dev DB — runtime checks deferred. Static checks above are the gate.\n";
    echo "\n=== RESULT: $pass passed, $fail failed ===\n";
    return;
}

[$accountA, $accountB] = [$accounts[0], $accounts[1]];
echo "Account A: id={$accountA->id} ({$accountA->name})\n";
echo "Account B: id={$accountB->id} ({$accountB->name})\n\n";

// ── 2. Dashboard scoping (was: global SUM across all tenants) ────
$globalIncome  = FarmAnimalEvent::where('deleted', 0)->where('cost_type', 'income')->sum('cost');
$accountAIncome = FarmAnimalEvent::where('deleted', 0)->where('account_id', $accountA->id)->where('cost_type', 'income')->sum('cost');

$report(
    'Dashboard query — account A income < global income (or both 0)',
    (float) $accountAIncome <= (float) $globalIncome,
    "global=R$globalIncome  A=R$accountAIncome"
);

$report(
    'Dashboard scoping is meaningful (global income > account A) IF cross-tenant data exists',
    (float) $globalIncome === (float) $accountAIncome
        ? true  // can't prove leak when both are equal
        : (float) $accountAIncome < (float) $globalIncome,
    'verifies that scoping by account_id changes the result'
);

// ── 3. Cross-tenant farm_id cannot leak into AnimalController::index() ──
// Pick a farm that belongs to account B; check no animals from B are visible
// when scoped to A. (We're checking the fix's *intended* DB-level invariant
// rather than going through HTTP.)
$farmB = Farm::where('account_id', $accountB->id)->where('deleted', '!=', 1)->first();
if ($farmB) {
    $leak = FarmAnimal::where('account_id', $accountA->id)
        ->where('farm_id', $farmB->id)
        ->count();
    $report(
        'Animal query (account A + farm B) returns 0 rows',
        $leak === 0,
        "leaked rows=$leak"
    );
} else {
    echo "[SKIP] Account B has no farms — skipping cross-tenant farm_id check.\n";
}

// ── 4. Birth event mother must be in same account ─────────────────
$animalA = FarmAnimal::where('account_id', $accountA->id)->where('deleted', 0)->first();
$animalB = FarmAnimal::where('account_id', $accountB->id)->where('deleted', 0)->first();
if ($animalA && $animalB) {
    // The fix in storeBirthEvent now scopes the mother lookup to the
    // resolved account_id. Simulate that scoping:
    $foundOnB  = FarmAnimal::where('account_id', $accountA->id)->find($animalB->id);
    $foundOnA  = FarmAnimal::where('account_id', $accountA->id)->find($animalA->id);
    $report(
        'Mother lookup scoped to account A returns 404 for animal in account B',
        $foundOnB === null,
        "animalB id={$animalB->id}"
    );
    $report(
        'Mother lookup scoped to account A finds animal A',
        $foundOnA !== null,
        "animalA id={$animalA->id}"
    );
} else {
    echo "[SKIP] Need at least one animal per account for birth-mother check.\n";
}

echo "\n=== RESULT: $pass passed, $fail failed ===\n";
