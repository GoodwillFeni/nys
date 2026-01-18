<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
class DeviceMessageController extends Controller
{
public function store(Request $request)
{
    $device = Device::where('device_uid', $request->device)
        ->where('api_key', $request->header('X-DEVICE-KEY'))
        ->first();

    if (!$device) {
        return response()->json(['message' => 'Unauthorized device'], 401);
    }

    $device->update([
        'last_seen_at' => now()
    ]);

    $type = $this->detectMessageType($request->all());

    $device->messages()->create([
        'type' => $type,
        'payload' => $request->all(),
        'device_timestamp' => now()->setTimestamp($request->timestamp),
    ]);

    return response()->json(['status' => 'ok']);
}

private function detectMessageType(array $data): string
{
    if (isset($data['gps'])) return 'location';
    if (isset($data['sensors'])) return 'sensor';
    return 'heartbeat';
}
}