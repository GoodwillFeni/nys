<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FarmAnimal extends Model
{
    use HasFactory;

    protected $table = 'farm_animals';

    protected $fillable = [
        'account_id',
        'farm_id',
        'animal_type_id',
        'breed_id',
        'animal_tag',
        'farm_tag',
        'sex',
        'date_of_birth',
        'animal_name',
        'status',
        'notes',
    ];

    // Relationships
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function animalType()
    {
        return $this->belongsTo(FarmAnimalType::class, 'animal_type_id');
    }

    public function breed()
    {
        return $this->belongsTo(AnimalBreed::class, 'breed_id');
    }

    public function events()
    {
        return $this->hasMany(AnimalEvent::class, 'animal_id');
    }

    // public function deviceLinks()
    // {
    //     return $this->hasMany(AnimalDeviceLink::class, 'animal_id');
    // }

    public function deviceLinks()
    {
        // Only return active links
        return $this->hasMany(AnimalDeviceLink::class, 'animal_id')
                    ->where('deleted', '!=', 1)
                    ->with('device'); // optionally eager-load the device
    }
}