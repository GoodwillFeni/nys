<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FarmAnimal extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'farm_id',
        'tag_number',
        'type',
        'breed',
        'age',
        'health_status',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
}
