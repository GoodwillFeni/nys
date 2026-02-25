<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\FarmTransaction;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function items(Request $request)
    {
        return InventoryItem::where('account_id', $request->account_id)->get();
    }

    public function movement(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'movement_type' => 'required|in:purchase,issue',
            'qty' => 'required|numeric|min:0.01'
        ]);

        return DB::transaction(function () use ($request) {

            $item = InventoryItem::findOrFail($request->inventory_item_id);

            $unitCost = $request->unit_cost;

            if ($request->movement_type === 'issue') {
                $totalPurchasedQty = InventoryMovement::where('inventory_item_id', $item->id)
                    ->where('movement_type', 'purchase')
                    ->sum('qty');

                $totalPurchasedValue = InventoryMovement::where('inventory_item_id', $item->id)
                    ->where('movement_type', 'purchase')
                    ->sum('total_cost');

                $avgCost = $totalPurchasedQty > 0
                    ? $totalPurchasedValue / $totalPurchasedQty
                    : 0;

                $unitCost = $avgCost;
            }

            $totalCost = $unitCost * $request->qty;

            $movement = InventoryMovement::create([
                'account_id' => $request->account_id,
                'farm_id' => $request->farm_id,
                'inventory_item_id' => $item->id,
                'animal_id' => $request->animal_id,
                'movement_type' => $request->movement_type,
                'qty' => $request->qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'movement_date' => now(),
                'created_by_user_id' => $request->user()->id
            ]);

            if ($request->movement_type === 'issue') {
                FarmTransaction::create([
                    'account_id' => $request->account_id,
                    'farm_id' => $request->farm_id,
                    'animal_id' => $request->animal_id,
                    'type' => 'expense',
                    'category' => 'inventory_usage',
                    'amount' => $totalCost,
                    'transaction_date' => now(),
                    'created_by_user_id' => $request->user()->id
                ]);
            }

            return $movement;
        });
    }
}
