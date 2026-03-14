<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnimalDeviceLink extends Model
{
    use HasFactory;
    
    protected $table = 'farm_animal_device_links';
    
    protected $fillable = [
        'account_id',
        'animal_id',
        'device_id',
        'linked_from',
        'linked_to',
        'deleted',
    ];

    protected $casts = [
        'linked_from' => 'datetime',
        'linked_to' => 'datetime',
        'deleted' => 'boolean',
    ];

    public function animal()
    {
        return $this->belongsTo(FarmAnimal::class, 'animal_id' , 'deleted' != 1);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
