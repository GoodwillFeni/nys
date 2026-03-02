<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Farm extends Model
{
    use HasFactory;

    protected $table = 'farm_farms';

    protected $fillable = [
        'account_id',
        'name',
        'location',
        'description',
        'is_active',
        'deleted'
    ];

    public function animals()
    {
        return $this->hasMany(FarmAnimal::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function reports()
    {
        return $this->hasMany(FarmReport::class);
    }
}