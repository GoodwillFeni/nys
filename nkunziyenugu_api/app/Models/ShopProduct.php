<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProduct extends Model
{
    protected $table = 'shop_products';

    protected $fillable = [
        'account_id',
        'product_name',
        'product_type',
        'description',
        'int_stock',
        'stock_level',
        'stock_price',
        'cal_price_no_prof',
        'cal_price',
        'actual_price',
        'prof_per_product',
        'img_path',
        'deleted',
    ];

    protected $casts = [
        'deleted' => 'boolean',
        'stock_price' => 'decimal:2',
        'cal_price_no_prof' => 'decimal:2',
        'cal_price' => 'decimal:2',
        'actual_price' => 'decimal:2',
        'prof_per_product' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
