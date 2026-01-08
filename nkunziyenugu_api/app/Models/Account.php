<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Account extends Model
{
    protected $fillable = [
        'name',
        'type',
        'deleted_flag',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'account_users')
                    ->withPivot('role')
                    ->wherePivot('deleted_flag', 0);
    }
}


