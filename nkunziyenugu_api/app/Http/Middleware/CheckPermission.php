<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gate a route behind a (routeName, action) tuple from the permissions
 * registry. Super admins bypass. Missing auth → 401. Missing account
 * context → 400. Insufficient permission → 403.
 *
 * Usage:
 *   Route::post('/farm/animals', [AnimalController::class, 'store'])
 *       ->middleware('permission:AddAnimal,add');
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $route, string $action)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        $accountId = $request->header('X-Account-ID');
        if (!$accountId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Active account not selected',
            ], 400);
        }

        if (!$user->canDo($route, $action, (int) $accountId)) {
            return response()->json([
                'status'  => 'error',
                'message' => "Forbidden \u2014 missing permission {$route}:{$action}",
            ], 403);
        }

        return $next($request);
    }
}
