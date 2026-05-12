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
    /**
     * List the accounts the caller can switch to. Super admins see all,
     * regular users see only accounts they belong to. Permission checks
     * are NOT applied here — this is the account picker that runs before
     * an X-Account-ID is known.
     */
    public function availableAccounts(Request $request)
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            $allAccounts = Account::where('deleted_flag', 0)
                ->select('id', 'name', 'type', 'created_at', 'updated_at')
                ->get();
            return response()->json(['accounts' => $allAccounts]);
        }

        $linkedAccounts = $user->accounts()->get();
        $accounts = $linkedAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'route_access'  => $account->pivot->route_access  ? (json_decode($account->pivot->route_access,  true) ?: []) : [],
                'action_access' => $account->pivot->action_access ? (json_decode($account->pivot->action_access, true) ?: []) : [],
                'created_at' => $account->pivot->created_at,
                'updated_at' => $account->pivot->updated_at,
            ];
        });

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * Self-service account creation. The caller becomes the owner of the new
     * account, with the preset that matches the chosen `type` (Home / Farm /
     * Shop / Other). Same lookup AuthController::register uses at signup so
     * the experience is consistent — Shop accounts get Shop routes, Farm
     * accounts get Farm routes, etc. Super admin can grant extra routes
     * later via EditPermissions.
     */
    public function createAccount(Request $request)
    {
        $authUser = $request->user();

        $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'nullable|in:Home,Farm,Shop,Other',
        ]);

        DB::beginTransaction();
        try {
            $type = $request->type ?? 'Home';

            $account = Account::create([
                'name' => $request->name,
                'type' => $type,
            ]);

            $presets  = require base_path('config/permissions_presets.php');
            $registry = require base_path('config/permissions_registry.php');
            $allRoutes  = array_map(fn($r) => $r['name'], $registry['routes']);
            $allActions = array_map(fn($a) => $a['name'], $registry['actions']);

            $presetByType = [
                'Home'  => 'Home',
                'Farm'  => 'Farm',
                'Shop'  => 'Shop',
                'Other' => 'Other',
            ];
            $presetName = $presetByType[$type] ?? 'Other';
            $owner      = $presets[$presetName];

            $routes  = $owner['routes']  === '*' ? $allRoutes  : $owner['routes'];
            $actions = $owner['actions'] === '*' ? $allActions : $owner['actions'];

            AccountUser::create([
                'account_id'    => $account->id,
                'user_id'       => $authUser->id,
                'route_access'  => $routes,
                'action_access' => $actions,
                'is_owner'      => true,
            ]);

            DB::commit();
            AuditLogService::logCreate($account, $request, "Created account: {$account->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Account created successfully',
                'data' => $account,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAccountDetails(Request $request, $id)
    {
        // Permission enforced by middleware: permission:Accounts,view
        $account = Account::where('id', $id)->where('deleted_flag', 0)->first();
        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Account not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at,
            ],
        ]);
    }

    public function updateAccount(Request $request, $id)
    {
        // Permission enforced by middleware: permission:EditAccount,edit
        $account = Account::where('id', $id)->where('deleted_flag', 0)->first();
        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Account not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'nullable|string|max:50',
        ]);

        try {
            $oldValues = $account->getAttributes();
            $account->update([
                'name' => $request->name,
                'type' => $request->type ?? $account->type,
            ]);
            AuditLogService::logUpdate($account, $oldValues, $request, "Updated account: {$account->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Account updated successfully',
                'data' => $account,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAccount(Request $request, $id)
    {
        // Permission enforced by middleware: permission:Accounts,delete
        $account = Account::where('id', $id)->where('deleted_flag', 0)->first();
        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Account not found or already deleted'], 404);
        }

        try {
            AuditLogService::logDelete($account, $request, "Deleted account: {$account->name}");
            $account->deleted_flag = 1;
            $account->save();
            return response()->json(['status' => 'success', 'message' => 'Account deleted successfully']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
