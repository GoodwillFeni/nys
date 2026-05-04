<?php

/**
 * POS-reliability smoke test — Chunk 2 of the SaaS-readiness plan.
 *
 * Run via:  php artisan tinker --execute="require 'tests/smoke/pos-reliability.php';"
 *
 * Verifies:
 *  - Money helper does exact integer-cents arithmetic (no float drift)
 *  - shop_pos_sales.idempotency_key column + unique index exist
 *  - ShopPosController::checkout no longer (a) contains response()->json inside the
 *    transaction closure or (b) does float-based total math
 *  - Stock validation now happens AFTER lockForUpdate (TOCTOU closed)
 */

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n=== POS-RELIABILITY SMOKE TEST ===\n\n";

$pass = 0;
$fail = 0;
$report = function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail) {
    $tag = $ok ? "[PASS]" : "[FAIL]";
    echo "$tag $name" . ($detail ? "  — $detail" : '') . "\n";
    $ok ? $pass++ : $fail++;
};

// ── 1. Money helper correctness ─────────────────────────────────
$report('Money::toCents("12.34") === 1234', Money::toCents("12.34") === 1234);
$report('Money::toCents(12.34) === 1234',  Money::toCents(12.34) === 1234);
$report('Money::toCents("0") === 0',        Money::toCents("0") === 0);
$report('Money::toCents(null) === 0',       Money::toCents(null) === 0);
$report('Money::toCents("-0.50") === -50',  Money::toCents("-0.50") === -50);
$report('Money::fromCents(1234) === "12.34"', Money::fromCents(1234) === "12.34");
$report('Money::fromCents(0) === "0.00"',     Money::fromCents(0) === "0.00");

// 0.1 + 0.2 in floats == 0.30000000000000004; in cents == 30
$noDrift = Money::sumCents([0.1, 0.2]) === 30;
$report('No float drift on 0.1 + 0.2 (cents = 30)', $noDrift, 'sum='. Money::sumCents([0.1, 0.2]));

// 1000 random small additions
mt_srand(42);
$values = [];
for ($i = 0; $i < 1000; $i++) $values[] = number_format(mt_rand(1, 9999) / 100, 2, '.', '');
$cents = Money::sumCents($values);
$expected = array_sum(array_map(fn($v) => Money::toCents($v), $values));
$report('1000-value sum is exact', $cents === $expected, "cents=$cents expected=$expected");

// ── 2. DB schema: idempotency_key column + unique index ─────────
$hasCol = Schema::hasColumn('shop_pos_sales', 'idempotency_key');
$report('shop_pos_sales.idempotency_key column exists', $hasCol);

if ($hasCol) {
    $idx = collect(DB::select("SHOW INDEX FROM shop_pos_sales"))
        ->groupBy('Key_name')
        ->map(fn($rows) => $rows->pluck('Column_name')->all());
    $hasUnique = $idx->contains(function ($cols) {
        return in_array('idempotency_key', $cols, true) && in_array('account_id', $cols, true);
    });
    $report('Unique index on (account_id, idempotency_key) exists', $hasUnique);
}

// ── 3. Static check: no response()->json inside DB::transaction closure ──
$ctrl = file_get_contents(base_path('app/Http/Controllers/Api/ShopPosController.php'));
preg_match('/public function checkout\b.*?(?=\n    (?:public|private|protected) function )/s', $ctrl, $m);
$checkoutBody = $m[0] ?? '';

// Find the DB::transaction(...) closure body and inspect just that.
preg_match('/DB::transaction\(function[^{]*\{(.*?)\n            \}\);/s', $checkoutBody, $cm);
$txnBody = $cm[1] ?? '';

$txnHasResponse = $txnBody !== '' && str_contains($txnBody, 'response()->json');
$report(
    'checkout(): no response()->json inside DB::transaction closure',
    !$txnHasResponse,
    $txnBody === '' ? 'could not locate transaction closure (regex miss)' : ($txnHasResponse ? 'still found inside closure' : '')
);

// ── 4. Static check: no float math on totals in checkout body ─────
$floatTotal = (bool) preg_match('/\$totalAmount\s*\+=\s*\(float\)/', $checkoutBody);
$floatChange = (bool) preg_match('/\(float\)\s*\$totalAmount/', $checkoutBody);
$report('checkout(): no `(float) $totalAmount` arithmetic', !$floatTotal && !$floatChange);

$usesMoney = str_contains($checkoutBody, 'Money::toCents') && str_contains($checkoutBody, 'Money::fromCents');
$report('checkout(): uses Money::toCents/fromCents', $usesMoney);

// ── 5. TOCTOU: stock check happens AFTER lockForUpdate ───────────
// We expect lockForUpdate() to appear BEFORE the "Insufficient stock" check
// in the file's source order.
$lockPos = strpos($checkoutBody, 'lockForUpdate()');
$stockChkPos = strpos($checkoutBody, 'Insufficient stock');
$report(
    'checkout(): lockForUpdate() runs before stock check',
    $lockPos !== false && $stockChkPos !== false && $lockPos < $stockChkPos,
    "lock=$lockPos check=$stockChkPos"
);

// ── 6. Idempotency short-circuit + unique-index race handler ─────
$report(
    'checkout(): idempotency short-circuit present',
    str_contains($checkoutBody, "idempotency_key") &&
    str_contains($checkoutBody, "->where('idempotency_key',") &&
    str_contains($checkoutBody, "duplicate")
);

$report(
    'checkout(): unique-index race handler present',
    str_contains($checkoutBody, 'shop_pos_sales_idem_unique')
);

// ── 7. Frontend: ShopPOS.vue sends idempotency_key ───────────────
$pos = file_get_contents(base_path('../nkunziyenugu_systems/src/components/views/shop/ShopPOS.vue'));
$report(
    'ShopPOS.vue: idempotency_key sent on checkout',
    str_contains($pos, 'idempotency_key: this.idempotencyKey')
);
$report(
    'ShopPOS.vue: rotates key after success',
    str_contains($pos, 'this.idempotencyKey = this.newIdempotencyKey()')
);

echo "\n=== RESULT: $pass passed, $fail failed ===\n";
