<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
    use BelongsToAccount;

    protected $table = 'shop_orders';

    protected $fillable = [
        'account_id',
        'user_id',
        'customer_id',
        'status',
        'total_amount',
        'notes',
        'payment_method',
        'payment_proof_path',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'paid_at',
    ];

    protected $casts = [
        'total_amount'  => 'decimal:2',
        'approved_at'   => 'datetime',
        'paid_at'       => 'datetime',
    ];

    // Statuses
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED         = 'approved';
    const STATUS_REJECTED         = 'rejected';
    const STATUS_COMPLETED        = 'completed';

    /** Every legal value the status column may hold. */
    public static function validStatuses(): array
    {
        return [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * The subset of statuses an admin can set on adminUpdate. Excludes
     * pending_approval — that's the initial state, never a transition target.
     */
    public static function adminTransitionStatuses(): array
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * Last line of defence: refuse to save a row with an unknown status,
     * even if a controller forgets to validate. Belt-and-braces guard
     * against bypass attacks (e.g. POSTing status='paid' directly).
     */
    protected static function booted(): void
    {
        static::saving(function (ShopOrder $order) {
            if ($order->status !== null && !in_array($order->status, self::validStatuses(), true)) {
                throw new \RuntimeException("Invalid order status '{$order->status}'");
            }
        });
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(ShopCustomer::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(ShopOrderItem::class, 'order_id');
    }

    // Revenue is counted only when payment is actually received:
    //   deposit      → approved or completed (deposit slip verified)
    //   pay_in_store → completed only (cash paid on collection)
    //   credit       → only when paid_at is set
    public function isPaidRevenue(): bool
    {
        return match($this->payment_method) {
            'deposit'      => in_array($this->status, [self::STATUS_APPROVED, self::STATUS_COMPLETED]),
            'pay_in_store' => $this->status === self::STATUS_COMPLETED,
            'credit'       => $this->paid_at !== null,
            default        => false,
        };
    }
}
