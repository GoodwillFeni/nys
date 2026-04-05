<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Farm;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalEvent;
use App\Models\FarmTransaction;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class FarmController extends Controller
{
    // List all farms for the account (excluding deleted)
    public function index(Request $request)
    {
        $accountId = (int) $request->header('X-Account-ID');

        $farms = Farm::where('account_id', $accountId)
                     ->where('deleted', '!=', 1)
                     ->get();

        return response()->json($farms);
    }

    // Create a new farm
    public function store(Request $request)
    {
        $accountId = (int) $request->header('X-Account-ID');

        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $farm = Farm::create([
            'account_id' => $accountId,
            'name' => $request->name,
            'location' => $request->location,
            'description' => $request->description ?? null,
            'is_active' => $request->is_active ?? true,
            'deleted' => 0
        ]);

        AuditLogService::logCreate($farm, $request, "Created farm: {$farm->name}");

        return response()->json([
            'status' => 'success',
            'data' => $farm,
        ], 201);
    }

    // Show a single farm
    public function show(Request $request, Farm $farm)
    {
        $accountId = (int) $request->header('X-Account-ID');
        $this->authorizeFarm($farm, $accountId);

        return response()->json($farm);
    }

    // Update a farm
    public function update(Request $request, Farm $farm)
    {
        $accountId = (int) $request->header('X-Account-ID');
        $this->authorizeFarm($farm, $accountId);

        $request->validate([
            'account_id' => 'sometimes|required|integer',
            'name' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        $farm->update($request->only('account_id', 'name', 'location', 'description', 'is_active'));

        $oldValues = $farm->getOriginal();
        AuditLogService::logUpdate($farm, $oldValues, $request, "Updated farm: {$farm->name}");
    
        return response()->json([
            'status' => 'success',
            'data' => $farm,
        ], 201);
    }

    // Soft delete a farm
    public function destroy(Request $request, Farm $farm)
    {
        $accountId = (int) $request->header('X-Account-ID');
        $this->authorizeFarm($farm, $accountId);

        // Use deleted flag instead of hard delete
        $farm->update(['deleted' => 1]);

        AuditLogService::logDelete($farm, $request, "Deleted farm: {$farm->name}");
        
        return response()->json(['message' => 'Farm deleted']);
    }

    // Dashboard summary
    public function dashboard(Request $request)
    {
        $accountId = $request->header('X-Account-ID');

        // Total farms
        $totalFarms = Farm::where('account_id', $accountId)->where('deleted', '!=', 1)->count();

        // Animals by status
        $animalsByStatus = FarmAnimal::where('account_id', $accountId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalAnimals = $animalsByStatus->sum();

        // Animals by type
        $animalsByType = FarmAnimal::withoutGlobalScopes()
            ->where('farm_animals.account_id', $accountId)
            ->where('farm_animals.deleted', '!=', 1)
            ->join('farm_animal_types', 'farm_animals.animal_type_id', '=', 'farm_animal_types.id')
            ->selectRaw('farm_animal_types.name as type_name, COUNT(*) as count')
            ->groupBy('farm_animal_types.name')
            ->pluck('count', 'type_name');

        // Animals per farm
        $animalsPerFarm = FarmAnimal::withoutGlobalScopes()
            ->where('farm_animals.account_id', $accountId)
            ->where('farm_animals.deleted', '!=', 1)
            ->join('farm_farms', 'farm_animals.farm_id', '=', 'farm_farms.id')
            ->selectRaw('farm_farms.name as farm_name, COUNT(*) as count')
            ->groupBy('farm_farms.name')
            ->pluck('count', 'farm_name');

        // Low stock items
        $inventoryItems = InventoryItem::where('account_id', $accountId)->where('deleted', 0)->get();
        $lowStockCount = $inventoryItems->filter(fn($item) => $item->low_stock)->count();

        // P&L for current month
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $eventPnl = FarmAnimalEvent::where('account_id', $accountId)
            ->where('deleted', 0)
            ->whereBetween('event_date', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN cost_type IN ('income','birth') THEN cost ELSE 0 END) as income,
                SUM(CASE WHEN cost_type IN ('expense','running') THEN cost ELSE 0 END) as expense,
                SUM(CASE WHEN cost_type = 'loss' THEN cost ELSE 0 END) as loss,
                SUM(CASE WHEN cost_type = 'investment' THEN cost ELSE 0 END) as investment
            ")
            ->first();

        // Inventory transactions for current month
        $txPnl = FarmTransaction::where('account_id', $accountId)
            ->where('deleted', 0)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
                SUM(CASE WHEN type = 'loss' THEN amount ELSE 0 END) as loss
            ")
            ->first();

        $monthIncome = round(($eventPnl->income ?? 0) + ($txPnl->income ?? 0), 2);
        $monthExpense = round(($eventPnl->expense ?? 0) + ($txPnl->expense ?? 0), 2);
        $monthLoss = round(($eventPnl->loss ?? 0) + ($txPnl->loss ?? 0), 2);
        $monthInvestment = round($eventPnl->investment ?? 0, 2);
        $monthProfit = round($monthIncome - $monthExpense - $monthLoss, 2);

        // Recent events (last 10)
        $recentEvents = FarmAnimalEvent::with(['animal:id,animal_tag,animal_name', 'farm:id,name'])
            ->where('account_id', $accountId)
            ->where('deleted', 0)
            ->latest('event_date')
            ->limit(10)
            ->get();

        return response()->json([
            'total_farms' => $totalFarms,
            'total_animals' => $totalAnimals,
            'animals_by_status' => $animalsByStatus,
            'animals_by_type' => $animalsByType,
            'animals_per_farm' => $animalsPerFarm,
            'low_stock_count' => $lowStockCount,
            'pnl' => [
                'income' => $monthIncome,
                'investment' => $monthInvestment,
                'expense' => $monthExpense,
                'loss' => $monthLoss,
                'profit' => $monthProfit,
                'period' => "$from to $to",
            ],
            'recent_events' => $recentEvents,
        ]);
    }

    // Authorization check
    private function authorizeFarm(Farm $farm, int $accountId)
    {
        if ($farm->account_id !== $accountId || $farm->deleted == 1) {
            abort(403, 'You are not authorized to access this farm.');
        }
    }
}