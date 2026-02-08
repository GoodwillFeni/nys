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
                'message' => 'Active account not selected'
            ], 400));
        }

        return $accountId;
    }

    protected function userHasAccountAccess(Request $request, int $accountId): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if ((int) ($user->is_super_admin ?? 0) === 1) {
            return true;
        }

        return $user->accounts()
            ->where('accounts.id', $accountId)
            ->exists();
    }

    protected function requireAccountAccess(Request $request, int $accountId): void
    {
        if (!$this->userHasAccountAccess($request, $accountId)) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this account'
            ], 403));
        }
    }

    protected function hasPrivilegedRole(Request $request, int $accountId): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if ((int) ($user->is_super_admin ?? 0) === 1) {
            return true;
        }

        return $user->accounts()
            ->where('accounts.id', $accountId)
            ->whereIn('account_users.role', ['Owner', 'Admin', 'owner', 'admin', 'SuperAdmin', 'Super_Admin', 'super_admin'])
            ->exists();
    }
}
