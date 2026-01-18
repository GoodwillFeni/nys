<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceMessage extends Model
{
    protected $fillable = [
        'device_id',
        'type',
        'payload',
        'device_timestamp',
    ];

    protected $casts = [
        'payload' => 'array',
        'device_timestamp' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
