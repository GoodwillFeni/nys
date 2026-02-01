<?php
namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceMessage;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessDeviceMessageJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public Device $device,
        public array $payload
    ) {}

    public function handle()
    {
        $type = $this->detectType();

        DeviceMessage::create([
            'device_id' => $this->device->id,
            'type' => $type,
            'payload' => $this->payload,
            'lat' => $this->payload['gps']['lat'] ?? null,
            'lng' => $this->payload['gps']['lng'] ?? null,
            'fix_quality' => $this->payload['gps']['fix_quality'] ?? null,
            'satellites' => $this->payload['gps']['satellites'] ?? null,
            'fix' => $this->payload['gps']['fix'] ?? null,
            'device_timestamp' => Carbon::createFromTimestamp(
                $this->payload['timestamp']
            ),
        ]);

        $this->device->update([
            'last_seen_at' => now()
        ]);
    }

    private function detectType()
    {
        if (isset($this->payload['gps'])) return 'location';
        if (isset($this->payload['sensors'])) return 'sensor';
        return 'heartbeat';
    }
}
