<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalType;
use App\Models\AnimalBreed;
use App\Services\AuditLogService;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        // determine account id from various sources: request body, header, authenticated user
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID')
            ?? optional($request->user())->account_id;

        $query = FarmAnimal::with(
                            'animalType',
                            'breed',
                            'farm',
                            'account',
                            //'events',
                            'deviceLinks'
                            );

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($request->farm_id) {
            $query->where('farm_id', $request->farm_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('animal_tag', 'like', "%{$request->search}%")
                  ->orWhere('farm_tag', 'like', "%{$request->search}%")
                  ->orWhere('animal_name', 'like', "%{$request->search}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(Request $request) // Create a new animal
    {
        $request->validate([
            'account_id' => 'required|integer',
            'farm_id' => 'required|integer|exists:farm_farms,id',
            'animal_type_id' => 'required|integer|exists:farm_animal_types,id',
            'animal_tag' => [
                'required',
                'numeric',
                'between:1,100000',
                // unique per farm_id/farm_tag
                function ($attribute, $value, $fail) use ($request) {
                    $exists = \App\Models\FarmAnimal::where('farm_id', $request->farm_id)
                        ->where('farm_tag', $value)
                        ->exists();
                    if ($exists) {
                        $fail('The '.$attribute.' has already been taken for this farm.');
                    }
                }
            ],
            'sex' => 'required|string',
            'date_of_birth' => 'required|date',
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'breed_id' => 'required|integer|exists:farm_animal_breeds,id',
        ]);

        return FarmAnimal::create([ // Create the animal record
            'account_id' => $request->account_id,
            'farm_id' => $request->farm_id,
            'animal_type_id' => $request->animal_type_id,
            'animal_tag' => $request->animal_tag,
            // mirror ear tag to farm tag to satisfy the unique constraint
            'farm_tag' => $request->animal_tag,
            'sex' => $request->sex,
            'date_of_birth' => $request->date_of_birth,
            'animal_name' => $request->name ?? null,
            'breed_id' => $request->breed_id,
            'status' => 'active',
            // 'notes' => $request->description ?? null,
            'notes' => $request->notes ?? $request->description ?? null, // always save to notes
        ]);

        AuditLogService::logCreate($animal, $request, "Created animal: {$animal->animal_name}");
    }

    public function types() // Fetch all animal types
    {
        $types = FarmAnimalType::where('deleted', '!=', 1)->get();

        return response()->json([
            'status' => 'success',
            'data' => $types
        ]);
    }
    public function storeType(Request $request) // Create a new animal type
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:farm_animal_types,name',
            'description' => 'nullable|string',
        ]);

        $type = FarmAnimalType::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($type, 201);
    }

    public function show(Request $request, FarmAnimal $animal) // Fetch a single animal
    {
        // Determine account ID (same logic as index)
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID')
            ?? optional($request->user())->account_id;

        if ($accountId && $animal->account_id != $accountId) {
            abort(403, 'Unauthorized access to this animal');
        }

        // Load all related data for editing/viewing
        return $animal->load(
            'animalType',
            'breed',
            'farm',
            'account',
            'deviceLinks',
            //'events'
        );
    }
    public function breeds(Request $request) // Fetch all animal breeds
    {
        $accountId = $request->header('X-Account-ID');

        $breeds = AnimalBreed::where('account_id', $accountId)
            ->when($request->animal_type_id, function ($query) use ($request) {
                $query->where('animal_type_id', $request->animal_type_id);
            })
            ->orderBy('breed_name')
            ->get();

        return response()->json($breeds);

        AuditLogService::logCreate($animal, $request, "Created animal: {$animal->animal_name}");
    }

    public function storeBreed(Request $request) // Create a new animal breed
    {
        $request->validate([
            'animal_type_id' => 'required|integer|exists:farm_animal_types,id',
            'breed_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $accountId = $request->account_id
            ?? $request->header('X-Account-ID');

        $breed = AnimalBreed::create([
            'account_id' => $accountId,
            'animal_type_id' => $request->animal_type_id,
            'breed_name' => $request->breed_name,
            'description' => $request->description,
        ]);

        return response()->json($breed, 201);
    }

public function update(Request $request, FarmAnimal $animal) // Update an animal
    {
        // Determine account ID (same logic as index)
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID')
            ?? optional($request->user())->account_id;

        if ($accountId && $animal->account_id != $accountId) {
            abort(403, 'Unauthorized access to this animal');
        }

        // Prepare update data
        $updateData = $request->all();

        // Map description to notes if notes not provided
        if ($request->has('description') && !$request->has('notes')) {
            $updateData['notes'] = $request->description;
        }

        // Validate the incoming data
        $request->validate([
            'animal_type_id' => 'sometimes|required|integer|exists:farm_animal_types,id',
            'animal_tag' => [
                'sometimes',
                'required',
                'numeric',
                'between:1,10000000',
                function ($attribute, $value, $fail) use ($animal) {
                    if ($value == $animal->animal_tag) return;

                    $exists = \App\Models\FarmAnimal::where('farm_id', $animal->farm_id)
                        ->where('farm_tag', $value)
                        ->where('id', '<>', $animal->id)
                        ->exists();
                    if ($exists) {
                        $fail('The '.$attribute.' has already been taken for this farm.');
                    }
                }
            ],
            'sex' => 'sometimes|required|string',
            'date_of_birth' => 'sometimes|required|date',
            'name' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
            'breed_id' => 'sometimes|required|integer|exists:farm_animal_breeds,id',
        ]);

        // Update using mapped data
        $oldValues = $animal->getOriginal();
        $animal->update($updateData); // <-- use $updateData here
        AuditLogService::logUpdate($animal, $oldValues, $request, "Updated animal: ");

        return $animal;
    }

public function destroy(Request $request, FarmAnimal $animal)
    {
        // Determine account ID
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID')
            ?? optional($request->user())->account_id;

        if ($accountId && $animal->account_id != $accountId) {
            abort(403, 'Unauthorized access to this animal');
        }

        $animal->update([
            'deleted' => 1
        ]);

        AuditLogService::logDelete($animal, $request, "Deleted animal:");

        return response()->json([
            'status' => 'success',
            'message' => 'Animal deleted successfully'
        ]);
    }
}