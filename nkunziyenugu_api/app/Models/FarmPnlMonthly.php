<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmPnlMonthly extends Model
{
    protected $table = 'farm_pnl_monthly';

    protected $fillable = [
        'account_id',
        'farm_id',
        'year',
        'month',
        'events_income',
        'events_expense',
        'events_running',
        'events_loss',
        'events_birth',
        'events_investment',
        'tx_income',
        'tx_expense',
        'tx_loss',
        'refreshed_at',
    ];

    protected $casts = [
        'year'              => 'integer',
        'month'             => 'integer',
        'events_income'     => 'decimal:2',
        'events_expense'    => 'decimal:2',
        'events_running'    => 'decimal:2',
        'events_loss'       => 'decimal:2',
        'events_birth'      => 'decimal:2',
        'events_investment' => 'decimal:2',
        'tx_income'         => 'decimal:2',
        'tx_expense'        => 'decimal:2',
        'tx_loss'           => 'decimal:2',
        'refreshed_at'      => 'datetime',
    ];
}
