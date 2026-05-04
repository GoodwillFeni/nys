<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class ShopCustomer extends Model
{
    use BelongsToAccount, SoftDeletesViaFlag;

    protected $table = 'shop_customers';

    protected $fillable = [
        'account_id',
        'user_id',
        'created_by_user_id',
        'updated_by_user_id',
        'name',
        'phone',
        'email',
        'credit_limit',
        'credit_used',
        'deleted',
    ];

    protected $casts = [
        'deleted'      => 'boolean',
        'credit_limit' => 'decimal:2',
        'credit_used'  => 'decimal:2',
    ];

    /** Available credit (limit minus used) — clamps to 0 if over. */
    public function getAvailableCreditAttribute()
    {
        $avail = (float) $this->credit_limit - (float) $this->credit_used;
        return $avail > 0 ? $avail : 0.0;
    }

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
