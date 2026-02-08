<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceMessage extends Model
{
    protected $fillable = [
        'device_id',
        'type',
        'payload',
        'lat',
        'lng',
        'message_timestamp',
    ];

    protected $casts = [
        'payload' => 'array',
        'message_timestamp' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
