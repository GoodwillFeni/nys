<?php

namespace App\Models;

use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class FarmAnimalType extends Model
{
    use SoftDeletesViaFlag;

    protected $table = 'farm_animal_types';
    protected $fillable = ['name', 'description', 'default_birth_value', 'deleted'];
    public $timestamps = true;
}