<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class FarmAnimalEvent extends Model
{
    use BelongsToAccount, SoftDeletesViaFlag;

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
        'created_by_user_id',
        // 'deleted' was previously missing — silent soft-delete failures.
        'deleted',
    ];

    protected $casts = [
        'meta' => 'array',
        'event_date' => 'date'
    ];

    public function animal()
    {
        return $this->belongsTo(FarmAnimal::class, 'animal_id');
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }
}