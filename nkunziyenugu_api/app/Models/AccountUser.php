<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountUser extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'route_access',
        'action_access',
        'deleted_flag',
    ];

    protected $casts = [
        'route_access'  => 'array',
        'action_access' => 'array',
    ];
}
