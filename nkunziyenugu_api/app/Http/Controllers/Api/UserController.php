<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\AuditLogService;

class UserController extends Controller
{
    /**
     * Validation rules for a per-account permission assignment.
     * route_access + action_access are validated against the registry
     * so nobody can grant a made-up permission.
     */
    protected function permissionValidationRules(): array
    {
        $registry = require base_path('config/permissions_registry.php');
        $routeNames  = array_map(fn($r) => $r['name'], $registry['routes']);
        $actionNames = array_map(fn($a) => $a['name'], $registry['actions']);

        return [
            'accounts' => 'required|array|min:1',
            'accounts.*.id' => 'required|exists:accounts,id',
            'accounts.*.route_access'    => 'nullable|array',
            'accounts.*.route_access.*'  => 'string|in:' . implode(',', $routeNames),
            'accounts.*.action_access'   => 'nullable|array',
            'accounts.*.action_access.*' => 'string|in:' . implode(',', $actionNames),
        ];
    }

    /**
     * Ensure caller is allowed to assign a given account to a user.
     * Super admins can assign any account; others only accounts they themselves have access to.
     */
    protected function assertCallerCanAssignAccount($authUser, int $accountId): void
    {
        if ($authUser->is_super_admin) return;
        $allowed = $authUser->accounts()->where('accounts.id', $accountId)->exists();
        if (!$allowed) {
            throw new \RuntimeException("Not allowed to assign account ID {$accountId}");
        }
    }

    /**
     * Add a new user and link to accounts with permissions.
     */
    public function addUser(Request $request)
    {
        $authUser = $request->user();

        $request->validate(array_merge([
            'name'     => 'required|string|max:150',
            'surname'  => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:50',
            'password' => 'required|min:6',
        ], $this->permissionValidationRules()));

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'          => $request->name,
                'surname'       => $request->surname,
                'email'         => $request->email,
                'phone'         => $request->phone,
                'password_hash' => Hash::make($request->password),
            ]);

            foreach ($request->accounts as $acc) {
                $this->assertCallerCanAssignAccount($authUser, (int) $acc['id']);
                $user->accounts()->attach($acc['id'], [
                    'route_access'  => json_encode($acc['route_access']  ?? []),
                    'action_access' => json_encode($acc['action_access'] ?? []),
                ]);
            }

            DB::commit();
            AuditLogService::logCreate($user, $request, "Created user: {$user->name} {$user->surname}");

            return response()->json([
                'status'  => 'success',
                'message' => 'User created and linked successfully',
                'user_id' => $user->id,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * List users scoped to the active account (or all users for super admin).
     * Permission enforced by middleware: permission:UserList,view
     */
    public function getUsers(Request $request)
    {
        $authUser = $request->user();
        $activeAccountId = $request->header('X-Account-ID');

        if (!$authUser->is_super_admin && !$activeAccountId) {
            return response()->json(['status' => 'error', 'message' => 'Active account not selected'], 400);
        }

        $query = DB::table('users as u')
            ->join('account_users as au', 'au.user_id', '=', 'u.id')
            ->join('accounts as a', 'a.id', '=', 'au.account_id')
            ->where('u.deleted_flag', 0)
            ->where('au.deleted_flag', 0);

        if (!$authUser->is_super_admin) {
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
            'au.route_access',
            'au.action_access'
        )
        ->orderBy('u.name')
        ->get()
        ->map(function ($row) {
            $row->route_access  = json_decode($row->route_access  ?? '[]', true) ?: [];
            $row->action_access = json_decode($row->action_access ?? '[]', true) ?: [];
            return $row;
        });

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    public function isImpersonating()
    {
        return session()->has('impersonator_id');
    }

    /**
     * Get a single user with accounts + permissions.
     * Permission enforced by middleware: permission:EditUser,view
     */
    public function getUserDetails(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::with(['accounts' => function ($q) use ($authUser) {
            if (!$authUser->is_super_admin) {
                $q->whereIn('accounts.id', $authUser->accounts->pluck('id'));
            }
            $q->select('accounts.id', 'accounts.name', 'accounts.type');
        }])->where('id', $id)->where('deleted_flag', 0)->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }

        $user->accounts->each(function ($account) {
            $account->route_access  = $account->pivot->route_access  ? (json_decode($account->pivot->route_access,  true) ?: []) : [];
            $account->action_access = $account->pivot->action_access ? (json_decode($account->pivot->action_access, true) ?: []) : [];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'surname'  => $user->surname,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'accounts' => $user->accounts,
            ],
        ]);
    }

    /**
     * Update user and sync account permissions.
     * Permission enforced by middleware: permission:EditUser,edit
     */
    public function updateUser(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('id', $id)->where('deleted_flag', 0)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }

        $request->validate(array_merge([
            'name'     => 'required|string|max:150',
            'surname'  => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email,' . $id,
            'phone'    => 'nullable|string|max:50',
            'password' => 'nullable|min:6',
        ], $this->permissionValidationRules()));

        DB::beginTransaction();
        try {
            $oldValues = $user->getAttributes();

            $user->update([
                'name'          => $request->name,
                'surname'       => $request->surname,
                'email'         => $request->email,
                'phone'         => $request->phone,
                'password_hash' => $request->password ? Hash::make($request->password) : $user->password_hash,
            ]);

            $syncData = [];
            foreach ($request->accounts as $acc) {
                $this->assertCallerCanAssignAccount($authUser, (int) $acc['id']);
                $syncData[$acc['id']] = [
                    'route_access'  => json_encode($acc['route_access']  ?? []),
                    'action_access' => json_encode($acc['action_access'] ?? []),
                ];
            }

            $user->accounts()->sync($syncData);

            DB::commit();
            AuditLogService::logUpdate($user, $oldValues, $request, "Updated user: {$user->name} {$user->surname}");

            return response()->json(['status' => 'success', 'message' => 'User updated successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Soft-delete user.
     * Permission enforced by middleware: permission:UserList,delete
     */
    public function deleteUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user || $user->deleted_flag) {
            return response()->json(['status' => 'error', 'message' => 'User not found or already deleted.'], 404);
        }

        try {
            AuditLogService::logDelete($user, $request, "Deleted user: {$user->name} {$user->surname}");
            $user->deleted_flag = 1;
            $user->save();
            return response()->json(['status' => 'success', 'message' => 'User deleted successfully.']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user: ' . $e->getMessage(),
            ], 500);
        }
    }
}
