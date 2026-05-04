<?php
namespace App\Models;

use App\Traits\BelongsToAccount;
use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use BelongsToAccount, SoftDeletesViaFlag;

    protected $softDeleteColumn = 'deleted_flag';

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
