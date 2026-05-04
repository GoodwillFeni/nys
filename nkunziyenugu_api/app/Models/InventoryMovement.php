<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use BelongsToAccount, SoftDeletesViaFlag;

    protected $table = 'farm_inventory_movements';

    protected $fillable = [
        'account_id',
        'farm_id',
        'inventory_item_id',
        'animal_id',
        'movement_type',
        'qty',
        'unit_cost',
        'total_cost',
        'movement_date',
        'notes',
        'created_by_user_id',
        'deleted',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function animal()
    {
        return $this->belongsTo(FarmAnimal::class, 'animal_id');
    }
}
