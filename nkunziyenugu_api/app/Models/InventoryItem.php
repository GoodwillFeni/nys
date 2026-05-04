<?php

namespace App\Models;

use App\Traits\BelongsToAccount;
use App\Traits\SoftDeletesViaFlag;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use BelongsToAccount, SoftDeletesViaFlag;

    protected $table = 'farm_inventory_items';

    protected $fillable = [
        'account_id',
        'farm_id',
        'name',
        'category',
        'unit',
        'reorder_level',
        'is_active',
        'deleted',
    ];

    public function farm()
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function getCurrentStockAttribute()
    {
        $purchased = $this->movements()
            ->where('movement_type', 'purchase')
            ->where('deleted', 0)
            ->sum('qty');

        $issued = $this->movements()
            ->where('movement_type', 'issue')
            ->where('deleted', 0)
            ->sum('qty');

        $adjusted = $this->movements()
            ->where('movement_type', 'adjustment')
            ->where('deleted', 0)
            ->sum('qty');

        return round($purchased - $issued + $adjusted, 4);
    }

    public function getLowStockAttribute()
    {
        if (!$this->reorder_level) return false;
        return $this->current_stock <= $this->reorder_level;
    }
}
