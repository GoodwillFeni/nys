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

        $epoch = null;
        if (isset($this->payload['message_timestamp'])) {
            $epoch = (int) $this->payload['message_timestamp'];
        } elseif (isset($this->payload['queued_at'])) {
            $epoch = (int) $this->payload['queued_at'];
        } elseif (isset($this->payload['timestamp'])) {
            $epoch = (int) $this->payload['timestamp'];
        }

        $messageTimestamp = now();
        if ($epoch !== null && $epoch >= 946684800 && $epoch <= 4102444800) {
            $messageTimestamp = Carbon::createFromTimestamp($epoch);
        }

        DeviceMessage::create([
            'device_id' => $this->device->id,
            'type' => $type,
            'payload' => $this->payload,
            'lat' => $this->payload['gps']['lat'] ?? null,
            'lng' => $this->payload['gps']['lng'] ?? null,
            'fix_quality' => $this->payload['gps']['fix_quality'] ?? null,
            'satellites' => $this->payload['gps']['satellites'] ?? null,
            'fix' => $this->payload['gps']['fix'] ?? null,
            'message_timestamp' => $messageTimestamp,
        ]);

        $this->device->update([
            'last_seen_at' => now()
        ]);
    }

    private function detectType()
    {
        if (isset($this->payload['gps'])) return 'location';
        if (isset($this->payload['sensors']) || isset($this->payload['inputs'])) return 'sensor';
        return 'heartbeat';
    }
}
