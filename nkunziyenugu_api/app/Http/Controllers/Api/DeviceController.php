<?php
// app/Http/Controllers/Api/DeviceController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Device::query()->with('account');
        if (!$user->is_super_admin) {
            $accountIds = $user->accounts()->pluck('accounts.id');
            $query->whereIn('account_id', $accountIds);
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('device_uid')) {
            $query->where('device_uid', 'like', '%' . $request->device_uid . '%');
        }
        $sortBy = $request->get('sort_by', 'created_at');
        $order  = $request->get('order', 'desc');

        if (!in_array($sortBy, ['name', 'device_uid', 'last_seen_at', 'created_at'])) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $order);

        return response()->json([
            'status' => 'success',
            'data' => $query->get()->map(function ($device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'device_uid' => $device->device_uid,
                    'alarm_enabled' => $device->alarm_enabled,
                    'last_seen_at' => $device->last_seen_at,
                    'account' => [
                        'id' => $device->account->id,
                        'name' => $device->account->name,
                    ],
                    'created_at' => $device->created_at,
                    'updated_at' => $device->updated_at,
                ];
            })
        ]);
    }
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name'       => 'required|string|max:150',
            'device_id'  => 'required|string|max:100|unique:devices,device_id',
            'alarms_enabled' => 'boolean',
        ]);

        // 🔐 Authorization
        if (!$user->is_super_admin) {
            $allowed = $user->accounts()
                ->where('accounts.id', $request->account_id)
                ->whereIn('account_users.role', ['owner', 'admin'])
                ->exists();

            if (!$allowed) {
                return response()->json([
                    'message' => 'Not allowed to add device to this account'
                ], 403);
            }
        }

        $device = Device::create([
            'account_id'      => $request->account_id,
            'name'            => $request->name,
            'device_id'       => $request->device_id,
            'api_key'         => hash('sha256', Str::random(60)),
            'alarms_enabled'  => $request->alarms_enabled ?? false,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $device
        ], 201);
    }

}
