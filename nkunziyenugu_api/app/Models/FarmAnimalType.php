<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmAnimalType extends Model
{
    protected $table = 'farm_animal_types';
    protected $fillable = ['name', 'description', 'deleted'];
    public $timestamps = true;
}