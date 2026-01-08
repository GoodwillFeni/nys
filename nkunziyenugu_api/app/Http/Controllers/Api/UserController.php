<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserController extends Controller
{
public function addUser(Request $request)
    {
        $authUser = $request->user();

        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'accounts' => 'required|array|min:1',
            'accounts.*.id' => 'required|exists:accounts,id',
            'accounts.*.role' => 'required|in:owner,admin,viewer',
        ]);

        DB::beginTransaction();

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'surname' => $request->surname,
                'email' => $request->email,
                'phone' => $request->phone,
                'password_hash' => Hash::make($request->password),
            ]);

            foreach ($request->accounts as $acc) {

                // Authorization check
                if (!$authUser->is_super_admin) {
                    $allowed = $authUser->accounts()
                        ->where('accounts.id', $acc['id'])
                        ->exists();

                    if (!$allowed) {
                        throw new \Exception('Not allowed to assign this account');
                    }
                }

                $user->accounts()->attach($acc['id'], [
                    'role' => $acc['role']
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'User created and linked successfully'
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 403);
        }
    }
}