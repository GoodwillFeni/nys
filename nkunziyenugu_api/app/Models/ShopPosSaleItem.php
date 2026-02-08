<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPosSaleItem extends Model
{
    protected $table = 'shop_pos_sale_items';

    protected $fillable = [
        'pos_sale_id',
        'product_id',
        'product_name',
        'product_type',
        'qty_sold',
        'actual_price',
        'total_price',
        'prof_per_product',
    ];

    protected $casts = [
        'actual_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'prof_per_product' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(ShopPosSale::class, 'pos_sale_id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }
}
