<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\FarmTransaction;
use App\Models\FarmAnimalEvent;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    // ── ITEMS ─────────────────────────────────────────────────────────────────

    public function items(Request $request)
    {
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID');

        $query = InventoryItem::where('deleted', 0);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        if ($request->farm_id) {
            $query->where('farm_id', $request->farm_id);
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }

        $items = $query->with('farm:id,name')->get();

        // Append computed stock to each item
        $items->each(function ($item) {
            $item->append(['current_stock', 'low_stock']);
        });

        return response()->json($items);
    }

    public function storeItem(Request $request)
    {
        $request->validate([
            'farm_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'category' => 'required|in:feed,vaccine,medicine,supplement,equipment,other',
            'unit' => 'required|string|max:50',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $accountId = $request->account_id
            ?? $request->header('X-Account-ID');

        $item = InventoryItem::create([
            'account_id' => $accountId,
            'farm_id' => $request->farm_id,
            'name' => $request->name,
            'category' => $request->category,
            'unit' => $request->unit,
            'reorder_level' => $request->reorder_level,
        ]);

        return response()->json($item, 201);
    }

    public function updateItem(Request $request, $id)
    {
        $item = InventoryItem::where('deleted', 0)->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|in:feed,vaccine,medicine,supplement,equipment,other',
            'unit' => 'sometimes|string|max:50',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $item->update($request->only(['name', 'category', 'unit', 'reorder_level']));

        return response()->json($item);
    }

    public function destroyItem(Request $request, $id)
    {
        $item = InventoryItem::where('deleted', 0)->findOrFail($id);
        $item->update(['deleted' => 1]);

        return response()->json(['message' => 'Item deleted']);
    }

    // ── MOVEMENTS ─────────────────────────────────────────────────────────────

    public function movements(Request $request)
    {
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID');

        $query = InventoryMovement::with([
                'item:id,name,unit,category',
                'animal:id,animal_tag,animal_name'
            ])
            ->where('deleted', 0);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        if ($request->farm_id) {
            $query->where('farm_id', $request->farm_id);
        }
        if ($request->inventory_item_id) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }
        if ($request->movement_type) {
            $query->where('movement_type', $request->movement_type);
        }
        if ($request->from && $request->to) {
            $query->whereBetween('movement_date', [$request->from, $request->to]);
        }

        return response()->json(
            $query->latest('movement_date')->paginate(50)
        );
    }

    public function movement(Request $request)
    {
        $request->validate([
            'farm_id' => 'required|integer',
            'inventory_item_id' => 'required|exists:farm_inventory_items,id',
            'movement_type' => 'required|in:purchase,issue,adjustment',
            'qty' => 'required|numeric|min:0.01',
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'animal_id' => 'nullable|integer',
        ]);

        $accountId = $request->account_id
            ?? $request->header('X-Account-ID');

        return DB::transaction(function () use ($request, $accountId) {

            $item = InventoryItem::findOrFail($request->inventory_item_id);
            $unitCost = $request->unit_cost;

            // For issues, calculate weighted average cost
            if ($request->movement_type === 'issue') {
                $totalPurchasedQty = InventoryMovement::where('inventory_item_id', $item->id)
                    ->where('movement_type', 'purchase')
                    ->where('deleted', 0)
                    ->sum('qty');

                $totalPurchasedValue = InventoryMovement::where('inventory_item_id', $item->id)
                    ->where('movement_type', 'purchase')
                    ->where('deleted', 0)
                    ->sum('total_cost');

                $unitCost = $totalPurchasedQty > 0
                    ? $totalPurchasedValue / $totalPurchasedQty
                    : 0;
            }

            $totalCost = $unitCost * $request->qty;

            $movement = InventoryMovement::create([
                'account_id' => $accountId,
                'farm_id' => $request->farm_id,
                'inventory_item_id' => $item->id,
                'animal_id' => $request->animal_id,
                'movement_type' => $request->movement_type,
                'qty' => $request->qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'movement_date' => now(),
                'notes' => $request->notes,
                'created_by_user_id' => $request->user()->id,
            ]);

            // On issue: create expense transaction
            if ($request->movement_type === 'issue') {
                FarmTransaction::create([
                    'account_id' => $accountId,
                    'farm_id' => $request->farm_id,
                    'animal_id' => $request->animal_id,
                    'type' => 'expense',
                    'category' => 'inventory_' . $item->category,
                    'amount' => $totalCost,
                    'transaction_date' => now(),
                    'notes' => "Issued {$request->qty} {$item->unit} of {$item->name}",
                    'created_by_user_id' => $request->user()->id,
                ]);

                // If linked to an animal, create an animal event
                if ($request->animal_id) {
                    $eventTypeMap = [
                        'feed' => 'Feeding',
                        'vaccine' => 'Vaccination',
                        'medicine' => 'Treatment',
                        'supplement' => 'Supplement',
                        'equipment' => 'Equipment',
                        'other' => 'Inventory Issue',
                    ];

                    FarmAnimalEvent::create([
                        'account_id' => $accountId,
                        'farm_id' => $request->farm_id,
                        'animal_id' => $request->animal_id,
                        'event_type' => $eventTypeMap[$item->category] ?? 'Inventory Issue',
                        'event_date' => now(),
                        'cost' => $totalCost,
                        'cost_type' => 'expense',
                        'meta' => json_encode([
                            'notes' => "Issued {$request->qty} {$item->unit} of {$item->name}",
                            'inventory_item' => $item->name,
                            'inventory_movement_id' => $movement->id,
                        ]),
                    ]);
                }
            }

            // On purchase: create expense transaction too
            if ($request->movement_type === 'purchase') {
                FarmTransaction::create([
                    'account_id' => $accountId,
                    'farm_id' => $request->farm_id,
                    'type' => 'expense',
                    'category' => 'inventory_purchase',
                    'amount' => $totalCost,
                    'transaction_date' => now(),
                    'notes' => "Purchased {$request->qty} {$item->unit} of {$item->name} @ R{$unitCost}/{$item->unit}",
                    'created_by_user_id' => $request->user()->id,
                ]);
            }

            return response()->json($movement, 201);
        });
    }
}
