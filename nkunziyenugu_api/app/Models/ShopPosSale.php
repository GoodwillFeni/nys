<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPosSale extends Model
{
    protected $table = 'shop_pos_sales';

    protected $fillable = [
        'account_id',
        'cashier_user_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'payment_method',
        'amount_received',
        'change_amount',
        'total_amount',
        'total_profit',
        'sale_datetime',
        'is_paid',
        'paid_at',
        'paid_method',
        'paid_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'sale_datetime' => 'datetime',
        'amount_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'paid_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(ShopPosSaleItem::class, 'pos_sale_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function customer()
    {
        return $this->belongsTo(ShopCustomer::class, 'customer_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
