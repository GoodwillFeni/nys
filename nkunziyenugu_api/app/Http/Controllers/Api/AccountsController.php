<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;

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
}
