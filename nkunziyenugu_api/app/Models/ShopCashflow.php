<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class ShopCashflow extends Model
{
    use BelongsToAccount, SoftDeletesViaFlag;

    protected $table = 'shop_cashflows';

    protected $fillable = [
        'account_id',
        'user_id',
        'updated_by_user_id',
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

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
