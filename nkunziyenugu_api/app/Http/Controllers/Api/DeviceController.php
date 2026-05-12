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
        // Eager-load latestHeartbeat so the UI can render firmware/balance
        // columns without per-row queries (Section E).
        $query = Device::query()->with(['account', 'latestHeartbeat']);

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

        // Optional dashboard-driven filters. Card on the Device Dashboard
        // links here with ?filter=outdated_firmware / low_balance / not_reporting.
        $devices = $query->get();
        $filter = $request->query('filter');
        if ($filter) {
            $latestFw      = config('devices.latest_firmware');
            $lowBalanceR   = config('devices.low_balance_threshold');
            $staleHours    = config('devices.stale_report_hours');
            $devices = $devices->filter(function ($d) use ($filter, $latestFw, $lowBalanceR, $staleHours) {
                $hb = $d->latestHeartbeat;
                switch ($filter) {
                    case 'outdated_firmware':
                        $fw = $hb?->payload['firmware_version'] ?? null;
                        return $fw !== null && $fw !== $latestFw;
                    case 'low_balance':
                        $bal = $hb?->payload['balance'] ?? null;
                        if ($bal === null) return false;
                        // Strip currency prefix / spaces. "R12.34" → 12.34
                        $num = (float) preg_replace('/[^0-9.\-]/', '', (string) $bal);
                        return $num < $lowBalanceR;
                    case 'not_reporting':
                        return !$d->last_seen_at || $d->last_seen_at->lt(now()->subHours($staleHours));
                    default:
                        return true;
                }
            })->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $devices->map(function ($device) {
                $hb = $device->latestHeartbeat;
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
                    'latest_heartbeat' => $hb ? [
                        'firmware_version' => $hb->payload['firmware_version'] ?? null,
                        'balance'          => $hb->payload['balance'] ?? null,
                        'balance_ts'       => $hb->payload['balance_ts'] ?? null,
                        'uptime_s'         => $hb->payload['uptime_s'] ?? null,
                        'created_at'       => $hb->created_at,
                    ] : null,
                    'created_at' => $device->created_at,
                    'updated_at' => $device->updated_at,
                ];
            })
        ]);
    }

    /**
     * Device Dashboard summary: counters for outdated firmware, low balance,
     * and devices not reporting, plus the 5 most recent messages from any
     * device in the active account. One endpoint = one query path for the
     * dashboard cards + activity strip.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $activeAccountId = (int) $request->header('X-Account-ID');

        // Scope: super admin sees all, regular users see active account only.
        $deviceQuery = Device::query()->with('latestHeartbeat');
        if (!$user->is_super_admin) {
            $deviceQuery->where('account_id', $activeAccountId);
        }
        $devices = $deviceQuery->get();

        $latestFw    = config('devices.latest_firmware');
        $lowBalanceR = config('devices.low_balance_threshold');
        $staleHours  = config('devices.stale_report_hours');
        $staleCutoff = now()->subHours($staleHours);

        $totals = [
            'all'                => $devices->count(),
            'outdated_firmware'  => 0,
            'low_balance'        => 0,
            'not_reporting'      => 0,
        ];
        foreach ($devices as $d) {
            $hb = $d->latestHeartbeat;
            if ($hb) {
                $fw = $hb->payload['firmware_version'] ?? null;
                if ($fw !== null && $fw !== $latestFw) $totals['outdated_firmware']++;
                $bal = $hb->payload['balance'] ?? null;
                if ($bal !== null) {
                    $num = (float) preg_replace('/[^0-9.\-]/', '', (string) $bal);
                    if ($num < $lowBalanceR) $totals['low_balance']++;
                }
            }
            if (!$d->last_seen_at || $d->last_seen_at->lt($staleCutoff)) {
                $totals['not_reporting']++;
            }
        }

        // Recent activity — latest 5 messages from any device in the account.
        $recentQuery = \App\Models\DeviceMessage::query()
            ->with('device:id,name,account_id')
            ->latest('created_at')
            ->limit(5);
        if (!$user->is_super_admin) {
            $recentQuery->whereHas('device', fn ($q) => $q->where('account_id', $activeAccountId));
        }
        $recent = $recentQuery->get()->map(function ($msg) {
            $payload = $msg->payload ?? [];
            $summary = match ($msg->type) {
                'heartbeat' => 'FW ' . ($payload['firmware_version'] ?? '?')
                             . (isset($payload['balance']) ? ', ' . $payload['balance'] : ''),
                'location'  => isset($msg->lat, $msg->lng) ? sprintf('%.4f, %.4f', $msg->lat, $msg->lng) : 'location',
                'sensor'    => 'sensor change',
                default     => (string) $msg->type,
            };
            return [
                'device_id'   => $msg->device?->id,
                'device_name' => $msg->device?->name,
                'type'        => $msg->type,
                'summary'     => $summary,
                'timestamp'   => $msg->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'totals'          => $totals,
                'recent_activity' => $recent,
                'thresholds'      => [
                    'latest_firmware'       => $latestFw,
                    'low_balance_threshold' => $lowBalanceR,
                    'stale_report_hours'    => $staleHours,
                ],
            ],
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
