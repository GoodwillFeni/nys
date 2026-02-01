<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
class DeviceMessageController extends Controller
{
public function store(Request $request)
{
    $deviceId = $request->header('X-DEVICE-ID');
    $signature = $request->header('X-DEVICE-SIGNATURE');
    $deviceKey = $request->header('X-DEVICE-KEY');

    if (!$deviceId || (!$signature && !$deviceKey)) {
        return response()->json(['message' => 'Unauthorized device'], 401);
    }

    $device = Device::where('device_uid', $deviceId)
        ->where('is_active', 1)
        ->first();

    if (!$device) {
        return response()->json(['message' => 'Invalid device'], 401);
    }

    if ($deviceKey) {
        if (!hash_equals((string) $device->device_secret, (string) $deviceKey)) {
            return response()->json(['message' => 'Invalid device key'], 401);
        }
    } else {
        $expected = hash_hmac(
            'sha256',
            $request->getContent(),
            $device->device_secret
        );

        if (!hash_equals($expected, (string) $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }
    }

    $device->update([
        'last_seen_at' => now()
    ]);

    $type = $this->detectMessageType($request->all());

    $payload = $request->all();

    $ts = (int) ($payload['timestamp'] ?? 0);
    if ($ts > 1000000000000) {
        $ts = (int) floor($ts / 1000);
    }
    $deviceTimestamp = now();
    if ($ts >= 946684800 && $ts <= 4102444800) {
        $deviceTimestamp = now()->setTimestamp($ts);
    }

    $device->messages()->create([
        'type' => $type,
        'payload' => $payload,
        'lat' => $payload['gps']['lat'] ?? null,
        'lng' => $payload['gps']['lng'] ?? null,
        'device_timestamp' => $deviceTimestamp,
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