<?php

namespace App\Models;

use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Account extends Model
{
    use SoftDeletesViaFlag;

    protected $softDeleteColumn = 'deleted_flag';

    protected $fillable = [
        'name',
        'type',
        'deleted_flag',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'account_users')
                    ->withPivot('route_access', 'action_access')
                    ->wherePivot('deleted_flag', 0);
    }
}


