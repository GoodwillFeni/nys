<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPosCartItem extends Model
{
    protected $table = 'shop_pos_cart_items';

    protected $fillable = [
        'pos_cart_id',
        'product_id',
        'qty',
        'unit_price',
        'total_price',
        'prof_per_product',
        'pre_stock_level',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'prof_per_product' => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(ShopPosCart::class, 'pos_cart_id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }
}
