<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
 public function impersonate(Request $request, $userId)
{
    $admin = $request->user();

    if (!$admin->is_super_admin) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    if ($admin->id == $userId) {
        return response()->json(['message' => 'Cannot impersonate yourself'], 400);
    }

    $user = User::findOrFail($userId);

    // Store original admin ID
    session([
        'impersonator_id' => $admin->id
    ]);

    // Optional audit
    DB::table('user_impersonations')->insert([
        'admin_id' => $admin->id,
        'impersonated_user_id' => $user->id,
    ]);

    Auth::login($user);

    return response()->json([
        'message' => 'Impersonation started',
        'user' => $user
    ]);
}

public function stop(Request $request)
{
    if (!session()->has('impersonator_id')) {
        return response()->json(['message' => 'Not impersonating'], 400);
    }

    $adminId = session()->pull('impersonator_id');
    $admin = User::findOrFail($adminId);

    // Optional audit close
    DB::table('user_impersonations')
        ->where('admin_id', $adminId)
        ->whereNull('ended_at')
        ->update(['ended_at' => now()]);

    Auth::login($admin);

    return response()->json([
        'message' => 'Returned to admin'
    ]);
}

}
