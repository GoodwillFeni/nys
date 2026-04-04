<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmTransaction extends Model
{
    protected $table = 'farm_transactions';

    protected $fillable = [
        'account_id',
        'farm_id',
        'animal_id',
        'type',
        'category',
        'amount',
        'transaction_date',
        'notes',
        'created_by_user_id',
        'deleted',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function animal()
    {
        return $this->belongsTo(FarmAnimal::class, 'animal_id');
    }
}
