<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnimalBreed extends Model
{
    protected $table = 'farm_animal_breeds';
    
    protected $fillable = [
        'account_id',
        'animal_type_id',
        'breed_name',
        'description',
    ];

    public function animalType()
    {
        return $this->belongsTo(FarmAnimalType::class, 'animal_type_id');
    }
}
