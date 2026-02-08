<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCashflow extends Model
{
    protected $table = 'shop_cashflows';

    protected $fillable = [
        'account_id',
        'user_id',
        'transaction_type',
        'payment_type',
        'amount',
        'date',
        'notes',
        'deleted',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
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
}
