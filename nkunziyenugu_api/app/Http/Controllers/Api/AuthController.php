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

            // 3️⃣ Link user to account as owner
            AccountUser::create([
                'account_id' => $account->id,
                'user_id'    => $user->id,
                'role'       => 'Owner',
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
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)
                        ->where('deleted_flag', 0)
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
            $accounts = $user->accounts()->withPivot('role')->get();

            return response()->json([
                'status' => 'success',
                'token' => $token->token,
                'expires_at' => $token->expires_at,
                'user'   => [
                    'id'      => $user->id,
                    'name'    => $user->name,
                    'surname' => $user->surname,
                    'email'   => $user->email,
                    'is_super_admin' => $user->is_super_admin,
                    'is_impersonating' => (bool) $user->is_super_admin, // ✅ true if 1, false if 0
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
