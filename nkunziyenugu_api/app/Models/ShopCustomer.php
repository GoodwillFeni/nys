<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCustomer extends Model
{
    protected $table = 'shop_customers';

    protected $fillable = [
        'account_id',
        'user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'name',
        'phone',
        'email',
        'deleted',
    ];

    protected $casts = [
        'deleted' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
