<?php

namespace App\Traits;

use Illuminate\Http\Request;

/**
 * Resolves the active account for the request and verifies the authenticated
 * user actually belongs to it. The header X-Account-ID is the only trusted
 * source — request body / query string can be set by the client and must
 * never be used to determine ownership.
 *
 * Mirrors the helpers on ShopBaseController so non-shop controllers can opt
 * in without inheriting from a shop-specific base.
 */
trait ResolvesAccount
{
    protected function requireActiveAccountId(Request $request): int
    {
        $accountId = (int) $request->header('X-Account-ID');

        if (!$accountId) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Active account not selected',
            ], 400));
        }

        return $accountId;
    }

    protected function userHasAccountAccess(Request $request, int $accountId): bool
    {
        $user = $request->user();
        if (!$user) return false;
        if ($user->is_super_admin) return true;

        return $user->accounts()
            ->where('accounts.id', $accountId)
            ->exists();
    }

    protected function requireAccountAccess(Request $request, int $accountId): void
    {
        if (!$this->userHasAccountAccess($request, $accountId)) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this account',
            ], 403));
        }
    }

    /**
     * Convenience: header-derive AND verify membership in one call. The
     * canonical entry point for any controller that needs the active account.
     */
    protected function resolveAccountId(Request $request): int
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);
        return $accountId;
    }
}
