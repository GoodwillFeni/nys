<?php

/**
 * One-shot script to strip every `if (!$this->hasPrivilegedRole(...)) { 403 }`
 * block from the shop controllers. Run via:
 *   php tests/smoke/strip-has-privileged-role.php
 *
 * The helper itself is then deleted from ShopBaseController by hand.
 *
 * Each block has the same structure but slightly different response messages,
 * so the regex matches the conditional + its single return inside.
 */

$files = [
    __DIR__ . '/../../app/Http/Controllers/Api/ShopCreditRequestController.php',
    __DIR__ . '/../../app/Http/Controllers/Api/ShopCashflowController.php',
    __DIR__ . '/../../app/Http/Controllers/Api/ShopCustomerController.php',
    __DIR__ . '/../../app/Http/Controllers/Api/ShopOrderController.php',
    __DIR__ . '/../../app/Http/Controllers/Api/ShopPosController.php',
    __DIR__ . '/../../app/Http/Controllers/Api/ShopProductController.php',
];

// Matches:
//
//   {indent}if (!$this->hasPrivilegedRole($request, $accountId)) {
//   {indent}    return response()->json([..something..], 403);
//   {indent}}
//
// plus an optional trailing blank line.
$pattern = '/^[ \t]*if \(!\$this->hasPrivilegedRole\(\$request, \$accountId\)\) \{\s*\n[ \t]*return response\(\)->json\(\[[^\]]*\], 403\);\s*\n[ \t]*\}\s*\n(\s*\n)?/m';

$total = 0;
foreach ($files as $f) {
    $src  = file_get_contents($f);
    $orig = $src;
    $src  = preg_replace($pattern, '', $src);
    $count = substr_count($orig, '$this->hasPrivilegedRole') - substr_count($src, '$this->hasPrivilegedRole');
    if ($count > 0) {
        file_put_contents($f, $src);
        echo "  $f -> stripped $count callers\n";
        $total += $count;
    }
}
echo "\nTotal stripped: $total\n";
