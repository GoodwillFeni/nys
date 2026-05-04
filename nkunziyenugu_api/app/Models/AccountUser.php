<?php

namespace App\Models;

use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class AccountUser extends Model
{
    use SoftDeletesViaFlag;

    protected $softDeleteColumn = 'deleted_flag';

    protected $fillable = [
        'account_id',
        'user_id',
        'route_access',
        'action_access',
        'is_owner',
        'deleted_flag',
    ];

    protected $casts = [
        'route_access'  => 'array',
        'action_access' => 'array',
        'is_owner'      => 'boolean',
    ];
}
