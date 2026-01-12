<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->is_super_admin) {
            return response()->json([
                'message' => 'Super admin access required'
            ], 403);
        }

        return $next($request);
    }
}
