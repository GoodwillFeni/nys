<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnimalEvent extends Model
{
    protected $table = 'farm_animal_events';

    protected $fillable = [
        'account_id',
        'farm_id',
        'animal_id',
        'event_type',
        'event_date',
        'meta',
        'created_by_user_id'
    ];

    protected $casts = [
        'event_date' => 'date',
        'meta' => 'json',
    ];
}