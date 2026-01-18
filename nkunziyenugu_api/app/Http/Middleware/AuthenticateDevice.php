<?php
namespace App\Http\Middleware;
use App\Models\Device;
use Closure;


class AuthenticateDevice
{
    public function handle($request, Closure $next)
    {
        $deviceId = $request->header('X-DEVICE-ID');
        $signature = $request->header('X-DEVICE-SIGNATURE');

        if (!$deviceId || !$signature) {
            return response()->json(['message' => 'Unauthorized device'], 401);
        }

        $device = Device::where('device_uid', $deviceId)
            ->where('is_active', 1)
            ->first();

        if (!$device) {
            return response()->json(['message' => 'Invalid device'], 401);
        }

        $expected = hash_hmac(
            'sha256',
            $request->getContent(),
            $device->device_secret
        );

        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $request->merge(['device' => $device]);
        return $next($request);
    }
}
