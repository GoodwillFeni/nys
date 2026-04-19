<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShopBaseController extends Controller
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

    /**
     * Does the user belong to (or can access) this account at all?
     * Super admins always do.
     */
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
     * Legacy helper — used to mean "Owner/Admin/SuperAdmin role on this account".
     * Kept as a compatibility shim after the permissions refactor so existing
     * controller code continues to work. New semantics: the user has at least
     * one write-type action (add/edit/delete/approve/complete) in this account.
     * Super admins always qualify.
     */
    protected function hasPrivilegedRole(Request $request, int $accountId): bool
    {
        $user = $request->user();
        if (!$user) return false;
        if ($user->is_super_admin) return true;

        foreach (['approve', 'complete', 'delete', 'edit', 'add'] as $action) {
            if ($user->hasAction($action, $accountId)) return true;
        }
        return false;
    }
}
