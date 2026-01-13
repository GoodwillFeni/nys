<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Account;
use App\Services\AuditLogService;

class UserController extends Controller
{
    /**
     * Add a new user and link to accounts
     */
    public function addUser(Request $request)
    {
        $authUser = $request->user();

        $request->validate([
            'name' => 'required|string|max:150',
            'surname' => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|min:6',
            'accounts' => 'required|array|min:1',
            'accounts.*.id' => 'required|exists:accounts,id',
            'accounts.*.role' => 'required|in:owner,admin,viewer',
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

            // 2️⃣ Attach accounts
            foreach ($request->accounts as $acc) {
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

            // Log the action
            AuditLogService::logCreate($user, $request, "Created user: {$user->name} {$user->surname}");

            return response()->json([
                'status' => 'success',
                'message' => 'User created and linked successfully',
                'user_id' => $user->id
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 403);
        }
    }

    public function getUsers(Request $request)
    {
        $authUser = $request->user();
        $activeAccountId = $request->header('X-Account-ID');

        if (!$this->isSuperAdmin($authUser) && !$activeAccountId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active account not selected'
            ], 400);
        }

        $query = DB::table('users as u')
            ->join('account_users as au', 'au.user_id', '=', 'u.id')
            ->join('accounts as a', 'a.id', '=', 'au.account_id')
            ->where('u.deleted_flag', 0)
            ->where('au.deleted_flag', 0);

        // Limit for normal users
        if (!$this->isSuperAdmin($authUser)) {
            $query->where('a.id', $activeAccountId);
        }

        $users = $query->select(
            'u.id as user_id',
            'u.name',
            'u.surname',
            'u.email',
            'u.phone',
            'u.created_at as user_created_at',
            'u.updated_at as user_updated_at',
            'a.id as account_id',
            'a.name as account_name',
            'a.type as account_type',
            'au.role as account_role'
        )
        ->orderBy('u.name')
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    /**
     * Check if user is SuperAdmin
     */
    private function isSuperAdmin($user): bool
    {
        return (bool) $user->is_super_admin;
    }

    /**
     * Check if impersonating
     */
    public function isImpersonating()
    {
        return session()->has('impersonator_id');
    }
/**
 * Get a user's details including accounts and roles
 */
public function getUserDetails(Request $request, $id)
{
    $authUser = $request->user();

    $user = User::with(['accounts' => function($q) use ($authUser) {
        if (!$authUser->is_super_admin) {
            // Normal users can only see accounts they themselves have
            $q->whereIn('accounts.id', $authUser->accounts->pluck('id'));
        }
        $q->select('accounts.id', 'accounts.name', 'accounts.type');
    }])->where('id', $id)
      ->where('deleted_flag', 0)
      ->first();

    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    // Add pivot role to each account
    $user->accounts->each(function($account) {
        $account->role = $account->pivot->role;
    });

    return response()->json([
        'status' => 'success',
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'phone' => $user->phone,
            'accounts' => $user->accounts
        ]
    ]);
}

/**
 * Update user details and accounts
 */
public function updateUser(Request $request, $id)
{
    $authUser = $request->user();

    $user = User::where('id', $id)->where('deleted_flag', 0)->first();
    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    $request->validate([
        'name' => 'required|string|max:150',
        'surname' => 'required|string|max:150',
        'email' => 'required|email|unique:users,email,' . $id,
        'phone' => 'nullable|string|max:50',
        'password' => 'nullable|min:6',
        'accounts' => 'required|array|min:1',
        'accounts.*.id' => 'required|exists:accounts,id',
        'accounts.*.role' => 'required|in:owner,admin,viewer,super_admin',
    ]);

    DB::beginTransaction();
    try {
        // Store old values for audit log
        $oldValues = $user->getAttributes();

        // Update basic info
        $user->update([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password_hash' => $request->password ? Hash::make($request->password) : $user->password_hash,
        ]);

        // Sync accounts
        $syncData = [];
        foreach ($request->accounts as $acc) {
            if (!$authUser->is_super_admin) {
                $allowed = $authUser->accounts()->where('accounts.id', $acc['id'])->exists();
                if (!$allowed) {
                    throw new \Exception("Not allowed to assign account ID {$acc['id']}");
                }
            }
            $syncData[$acc['id']] = ['role' => $acc['role']];
        }

        $user->accounts()->sync($syncData);

        DB::commit();

        // Log the action
        AuditLogService::logUpdate($user, $oldValues, $request, "Updated user: {$user->name} {$user->surname}");

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully'
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 403);
    }
}
// Delete user (soft delete)
public function deleteUser(Request $request, $id)
{
    $authUser = $request->user();

    // Only super admins can delete users
    if (!$authUser->is_super_admin) {
        return response()->json([
            'status' => 'error',
            'message' => 'You do not have permission to delete users.'
        ], 403);
    }

    $user = User::find($id);

    if (!$user || $user->deleted_flag) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found or already deleted.'
        ], 404);
    }

    try {
        // Log before deletion
        AuditLogService::logDelete($user, $request, "Deleted user: {$user->name} {$user->surname}");

        $user->deleted_flag = 1;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully.'
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete user: ' . $e->getMessage()
        ], 500);
    }
}

}
