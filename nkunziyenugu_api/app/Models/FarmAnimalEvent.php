<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmAnimalEvent extends Model
{
    protected $table = 'farm_animal_events';

    protected $fillable = [
        'account_id',
        'farm_id',
        'animal_id',
        'event_type',
        'event_date',
        'cost',
        'cost_type',
        'batch_id',
        'meta',
        'created_by_user_id'
    ];

    protected $casts = [
        'meta' => 'array',
        'event_date' => 'date'
    ];
}