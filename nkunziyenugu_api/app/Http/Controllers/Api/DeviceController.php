<?php
// app/Http/Controllers/Api/DeviceController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $activeAccountId = $request->header('X-Account-ID');
        $query = Device::query()->with('account');

        // Data scoping: super admin sees all; regular users see only devices
        // in the active account (permission already checked by middleware).
        if (!$user->is_super_admin) {
            $query->where('account_id', (int) $activeAccountId);
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
                    'has_alarm' => $device->has_alarm,
                    'last_seen_at' => $device->last_seen_at,
                    'account' => $device->account ? [
                        'id' => $device->account->id,
                        'name' => $device->account->name,
                    ] : null,
                    'account_name' => $device->account ? $device->account->name : null,
                    'created_at' => $device->created_at,
                    'updated_at' => $device->updated_at,
                ];
            })
        ]);
    }

    public function logs(Request $request, Device $device)
    {
        $user = $request->user();
        $activeAccountId = (int) $request->header('X-Account-ID');

        // Data scoping: the device must belong to the active account (permission
        // to VIEW logs already checked by middleware).
        if (!$user->is_super_admin && $device->account_id !== $activeAccountId) {
            return response()->json(['message' => 'Not allowed to view logs for this device'], 403);
        }

        $query = $device->messages();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from')) {
            $from = Carbon::parse($request->from);
            $query->whereRaw('COALESCE(message_timestamp, created_at) >= ?', [$from]);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse($request->to);
            $query->whereRaw('COALESCE(message_timestamp, created_at) <= ?', [$to]);
        }

        $query->orderByRaw('COALESCE(message_timestamp, created_at) desc')
            ->orderBy('created_at', 'desc');

        $perPage = (int) $request->get('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $maxPerPage = 5000;
        if ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items())->map(function ($msg) use ($device) {
            return [
                'id' => $msg->id,
                'type' => $msg->type,
                'payload' => $msg->payload,
                'lat' => $msg->lat,
                'lng' => $msg->lng,
                'message_timestamp' => $msg->message_timestamp,
                'created_at' => $msg->created_at,
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'device_uid' => $device->device_uid,
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }
    
    public function store(Request $request)
    {
        $user = $request->user();

        $name = $request->input('device_asset_name', $request->input('name'));

        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name'       => 'required_without:device_asset_name|string|max:150',
            'device_asset_name' => 'required_without:name|string|max:150',
            'device_uid'  => 'required|string|max:100|unique:devices,device_uid',
            'device_key'  => 'required|string|max:255',
            'has_alarm' => 'boolean',
        ]);

        // Authorization: middleware already confirmed AddDevice + add permission
        // in the active account. Still enforce that the supplied account_id
        // matches the active account (can't add a device to a different account).
        $activeAccountId = (int) $request->header('X-Account-ID');
        if (!$user->is_super_admin && (int) $request->account_id !== $activeAccountId) {
            return response()->json(['message' => 'account_id must match active account'], 403);
        }

        $plainSecret = $request->device_key;

        $device = Device::create([
            'account_id'      => $request->account_id,
            'name'            => $name,
            'device_uid'      => $request->device_uid,
            'device_secret'   => $plainSecret,
            'has_alarm'       => $request->has_alarm ?? false,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $device,
        ], 201);
    }

}
