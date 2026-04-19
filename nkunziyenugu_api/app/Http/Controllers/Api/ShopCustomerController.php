<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountUser;
use App\Models\ShopCustomer;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ShopCustomerController extends ShopBaseController
{
    public function index(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $search = trim((string) $request->get('search', ''));

        $q = ShopCustomer::query()
            ->where('account_id', $accountId)
            ->where('deleted', false);

        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $rows = $q->orderBy('name')->limit(50)->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:150|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $actorUserId = $request->user()->id;

        return DB::transaction(function () use ($request, $accountId, $actorUserId) {
            $existing = ShopCustomer::where('account_id', $accountId)
                ->where('phone', $request->phone)
                ->where('deleted', false)
                ->first();

            if ($existing) {
                return response()->json(['status' => 'error', 'message' => 'Customer with this phone already exists'], 422);
            }

            $existingUserPhone = User::query()
                ->where('deleted_flag', 0)
                ->where('phone', $request->phone)
                ->first();

            if ($existingUserPhone) {
                return response()->json(['status' => 'error', 'message' => 'Phone number is already used by another user'], 422);
            }

            $email = $request->email;
            if (!$email) {
                $digits = preg_replace('/\D+/', '', (string) $request->phone);
                $email = 'customer_' . $accountId . '_' . $digits . '@local';
            }

            $user = User::create([
                'name' => $request->name,
                'surname' => '-',
                'email' => $email,
                'phone' => $request->phone,
                'password_hash' => Hash::make($request->password),
            ]);

            $presets  = require base_path('config/permissions_presets.php');
            $registry = require base_path('config/permissions_registry.php');
            $allRoutes  = array_map(fn($r) => $r['name'], $registry['routes']);
            $allActions = array_map(fn($a) => $a['name'], $registry['actions']);
            $customerPreset = $presets['Customer'];
            $routes  = $customerPreset['routes']  === '*' ? $allRoutes  : $customerPreset['routes'];
            $actions = $customerPreset['actions'] === '*' ? $allActions : $customerPreset['actions'];

            AccountUser::create([
                'account_id'    => $accountId,
                'user_id'       => $user->id,
                'route_access'  => $routes,
                'action_access' => $actions,
                'deleted_flag'  => 0,
            ]);

            $customer = ShopCustomer::create([
                'account_id' => $accountId,
                'user_id' => $user->id,
                'created_by_user_id' => $actorUserId,
                'updated_by_user_id' => null,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'deleted' => false,
            ]);

            AuditLogService::logCreate($customer, $request, 'Created shop customer');

            return response()->json(['status' => 'success', 'data' => $customer], 201);
        });
    }
}
