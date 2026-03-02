<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Farm;
use App\Services\AuditLogService;

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

    // Authorization check
    private function authorizeFarm(Farm $farm, int $accountId)
    {
        if ($farm->account_id !== $accountId || $farm->deleted == 1) {
            abort(403, 'You are not authorized to access this farm.');
        }
    }
}