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

        // Load accounts with pivot role
        $accounts = $user->accounts()->withPivot('role')->get();
        // If user has any account where role = Super_admin, show all accounts
        $isSuperAdmin = $accounts->contains(function ($account) {
            return $account->pivot->role === 'SuperAdmin';
        });

        if ($isSuperAdmin) {
            $allAccounts = Account::select('id', 'name')->get();
            return response()->json(['accounts' => $allAccounts]);
        }

        // Otherwise, only show linked accounts
        $linkedAccounts = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'role' => $account->pivot->role,
            ];
        });

        return response()->json(['accounts' => $linkedAccounts]);
    }
}

