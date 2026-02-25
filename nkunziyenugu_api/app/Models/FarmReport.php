<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FarmReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'report_type',
        'title',
        'description',
        'report_date',
    ];

    protected $dates = [
        'report_date',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
}
