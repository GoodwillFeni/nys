<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'device_uid',
        'device_secret',
        'account_id',
        'has_alarm',
        'last_seen_at',
        'is_active',
        'deleted_flag',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(DeviceMessage::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
