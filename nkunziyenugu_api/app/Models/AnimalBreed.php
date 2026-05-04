<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class AnimalBreed extends Model
{
    use BelongsToAccount;

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
