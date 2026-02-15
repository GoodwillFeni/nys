<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCreditRequest extends Model
{
    protected $table = 'shop_credit_requests';

    protected $fillable = [
        'account_id',
        'customer_id',
        'amount_requested',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function customer()
    {
        return $this->belongsTo(ShopCustomer::class, 'customer_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
