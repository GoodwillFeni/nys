<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDeviceMessageJob;
use Illuminate\Http\Request;

class DeviceIngestController extends Controller
{
    public function store(Request $request)
    {
        $device = $request->device;

        ProcessDeviceMessageJob::dispatch($device, $request->all());

        return response()->json(['status' => 'ok']);
    }
}
