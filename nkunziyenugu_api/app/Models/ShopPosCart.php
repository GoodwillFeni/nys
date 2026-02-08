<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPosCart extends Model
{
    protected $table = 'shop_pos_carts';

    protected $fillable = [
        'account_id',
        'cashier_user_id',
        'customer_name',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(ShopPosCartItem::class, 'pos_cart_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }
}
