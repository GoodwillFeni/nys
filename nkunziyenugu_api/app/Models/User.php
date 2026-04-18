<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Notifications\ResetPasswordNotification;


class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'surname',
        'email',
        'phone',
        'password_hash',
        'deleted_flag'
    ];

    protected $hidden = [
        'password_hash'
    ];

    //Get password attribute
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    //User assignments relationship
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_users')
                    ->withPivot('role', 'can_manage_devices')
                    ->wherePivot('deleted_flag', 0);
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
