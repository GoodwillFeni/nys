<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\Account;
use App\Models\AccountUser;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:150',
            'surname'  => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:50',
            'password' => 'required|min:6'
        ]);

        DB::beginTransaction();

        try {
            // 1️⃣ Create user
            $user = User::create([
                'name'          => $request->name,
                'surname'       => $request->surname,
                'email'         => $request->email,
                'phone'         => $request->phone,
                'password_hash' => Hash::make($request->password),
            ]);

            // 2️⃣ Create account automatically
            $account = Account::create([
                'name' => $request->name . "'s Account",
                'type' => 'Home',
            ]);

            // 3️⃣ Link user to account with full Owner permissions (all routes + all actions)
            $presets  = require base_path('config/permissions_presets.php');
            $registry = require base_path('config/permissions_registry.php');
            $allRoutes  = array_map(fn($r) => $r['name'], $registry['routes']);
            $allActions = array_map(fn($a) => $a['name'], $registry['actions']);
            $ownerPreset = $presets['Owner'];
            $routes  = $ownerPreset['routes']  === '*' ? $allRoutes  : $ownerPreset['routes'];
            $actions = $ownerPreset['actions'] === '*' ? $allActions : $ownerPreset['actions'];

            AccountUser::create([
                'account_id'    => $account->id,
                'user_id'       => $user->id,
                'route_access'  => json_encode($routes),
                'action_access' => json_encode($actions),
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Account and user created successfully',
                'user'    => $user,
                'account' => $account
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Registration failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
        {
            $request->validate([
                'login'    => 'nullable|string',
                'email'    => 'nullable|string',
                'password' => 'required',
            ]);

            $login = (string) ($request->login ?? $request->email ?? '');
            $login = trim($login);

            if ($login === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Login is required',
                ], 422);
            }

            $user = User::query()
                ->where('deleted_flag', 0)
                ->where(function ($q) use ($login) {
                    $q->where('email', $login)->orWhere('phone', $login);
                })
                ->first();

            if (!$user || !Hash::check($request->password, $user->password_hash)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid login credentials',
                ], 401);
            }

           // $token = $user->createToken('api-token')->plainTextToken;
           $token = $user->tokens()->create([
                'name' => 'api-token',
                'token' => $user->createToken('api-token')->plainTextToken,
                'abilities' => ['*'],
                'expires_at' => now()->addHours(12), // Token expires in 12 hours
            ]);
            $accounts = $user->accounts()->withPivot('route_access', 'action_access')->get()
                ->map(function ($a) {
                    $a->pivot->route_access  = json_decode($a->pivot->route_access  ?? '[]', true) ?: [];
                    $a->pivot->action_access = json_decode($a->pivot->action_access ?? '[]', true) ?: [];
                    return $a;
                });

            return response()->json([
                'status' => 'success',
                'token' => $token->token,
                'expires_at' => $token->expires_at,
                'user'   => [
                    'id'      => $user->id,
                    'name'    => $user->name,
                    'surname' => $user->surname,
                    'email'   => $user->email,
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'is_impersonating' => session()->has('impersonator_id'),
                ],
                'accounts' => $accounts
            ]);
        }
    public function logout(Request $request)
        {
            $user = $request->user();
            $user->tokens()->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Logged out successfully',
            ]);
        }
}
