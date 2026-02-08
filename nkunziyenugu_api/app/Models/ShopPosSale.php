<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPosSale extends Model
{
    protected $table = 'shop_pos_sales';

    protected $fillable = [
        'account_id',
        'cashier_user_id',
        'customer_name',
        'total_amount',
        'total_profit',
        'sale_datetime',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'sale_datetime' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ShopPosSaleItem::class, 'pos_sale_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
