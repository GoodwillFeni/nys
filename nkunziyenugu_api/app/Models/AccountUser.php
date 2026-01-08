<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountUser extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'deleted_flag',
    ];
}
