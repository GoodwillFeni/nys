<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Account;
use App\Models\AccountUser;
use App\Services\AuditLogService;

class AccountsController extends Controller
{
    public function availableAccounts(Request $request)
    {
        $user = $request->user();

        // Check if the user is super admin
        if ($user->is_super_admin == 1) {
            // Return all accounts
            $allAccounts = Account::where('deleted_flag', 0)
                                  ->select('id', 'name', 'type', 'created_at', 'updated_at')
                                  ->get();
            return response()->json(['accounts' => $allAccounts]);
        }

        // Otherwise, return only linked accounts
        $linkedAccounts = $user->accounts()->get(); // Load linked accounts via pivot
        $accounts = $linkedAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'role' => $account->pivot->role,
                'created_at' => $account->pivot->created_at,
                'updated_at' => $account->pivot->updated_at
            ];
        });

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * Create a new account
     */
    public function createAccount(Request $request)
    {
        $authUser = $request->user();

        $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        try {
            // Create account
            $account = Account::create([
                'name' => $request->name,
                'type' => $request->type ?? 'Home',
            ]);

            // Link creator as owner
            AccountUser::create([
                'account_id' => $account->id,
                'user_id' => $authUser->id,
                'role' => 'Owner',
            ]);

            DB::commit();

            // Log the action
            AuditLogService::logCreate($account, $request, "Created account: {$account->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Account created successfully',
                'data' => $account
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account details
     */
    public function getAccountDetails(Request $request, $id)
    {
        $authUser = $request->user();

        $account = Account::where('id', $id)
                          ->where('deleted_flag', 0)
                          ->first();

        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not found'
            ], 404);
        }

        // Check permissions: super admin or user linked to account
        if (!$authUser->is_super_admin) {
            $hasAccess = $authUser->accounts()
                ->where('accounts.id', $id)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this account'
                ], 403);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at
            ]
        ]);
    }

    /**
     * Update account
     */
    public function updateAccount(Request $request, $id)
    {
        $authUser = $request->user();

        $account = Account::where('id', $id)
                          ->where('deleted_flag', 0)
                          ->first();

        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not found'
            ], 404);
        }

        // Check permissions: super admin or owner of the account
        if (!$authUser->is_super_admin) {
            $userAccount = $authUser->accounts()
                ->where('accounts.id', $id)
                ->first();

            if (!$userAccount || $userAccount->pivot->role !== 'owner') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only account owners can update accounts'
                ], 403);
            }
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'nullable|string|max:50',
        ]);

        try {
            // Store old values for audit log
            $oldValues = $account->getAttributes();

            $account->update([
                'name' => $request->name,
                'type' => $request->type ?? $account->type,
            ]);

            // Log the action
            AuditLogService::logUpdate($account, $oldValues, $request, "Updated account: {$account->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Account updated successfully',
                'data' => $account
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete account (soft delete)
     */
    public function deleteAccount(Request $request, $id)
    {
        $authUser = $request->user();

        $account = Account::where('id', $id)
                          ->where('deleted_flag', 0)
                          ->first();

        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not found or already deleted'
            ], 404);
        }

        // Only super admins can delete accounts
        if (!$authUser->is_super_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete accounts'
            ], 403);
        }

        try {
            // Log before deletion
            AuditLogService::logDelete($account, $request, "Deleted account: {$account->name}");

            $account->deleted_flag = 1;
            $account->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Account deleted successfully'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
