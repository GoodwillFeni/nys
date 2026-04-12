<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Account;

class EnsureAccountAccess
{
    public function handle(Request $request, Closure $next)
    {
        $accountId = $request->header('X-Account-ID');
        $user      = $request->user();

        // Super admin: X-Account-ID is optional — they can access any account
        if ($user->is_super_admin ?? false) {
            $request->merge(['account_id' => $accountId]);
            return $next($request);
        }

        // Regular users must supply X-Account-ID
        if (!$accountId) {
            return response()->json(['message' => 'X-Account-ID header required'], 400);
        }

        $hasAccess = $user->accounts()
            ->where('accounts.id', $accountId)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized account access'], 403);
        }

        $request->merge(['account_id' => $accountId]);

        return $next($request);
    }
}
