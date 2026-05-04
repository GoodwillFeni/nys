<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Farm;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalType;
use App\Models\FarmAnimalEvent;
use App\Models\AnimalBreed;
use App\Services\AuditLogService;
use App\Traits\ResolvesAccount;

class AnimalController extends Controller
{
    use ResolvesAccount;

    public function index(Request $request)
    {
        $accountId = $this->resolveAccountId($request);

        $query = FarmAnimal::with([
                            'animalType',
                            'breed',
                            'farm',
                            'account',
                            'deviceLinks',
                            'mother:id,animal_tag,animal_name',
                            ]);

        $query->where('account_id', $accountId);

        if ($request->farm_id) {
            // Reject filters pointing at another tenant's farm — the AND
            // account_id above would silently return zero rows otherwise,
            // but a 403 makes the misuse obvious.
            $farmInAccount = Farm::where('id', $request->farm_id)
                ->where('account_id', $accountId)
                ->where('deleted', '!=', 1)
                ->exists();
            abort_unless($farmInAccount, 403, 'Farm does not belong to this account');
            $query->where('farm_id', $request->farm_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->mother_id) {
            $query->where('mother_id', $request->mother_id);
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
        $accountId = $this->resolveAccountId($request);

        $request->validate([
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

        // Farm must belong to the active account.
        $farmInAccount = Farm::where('id', $request->farm_id)
            ->where('account_id', $accountId)
            ->where('deleted', '!=', 1)
            ->exists();
        abort_unless($farmInAccount, 403, 'Farm does not belong to this account');

        return FarmAnimal::create([ // Create the animal record
            'account_id' => $accountId,
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
            'default_birth_value' => 'nullable|numeric|min:0',
        ]);

        $type = FarmAnimalType::create([
            'name' => $request->name,
            'description' => $request->description,
            'default_birth_value' => $request->default_birth_value ?? 0,
        ]);

        return response()->json($type, 201);
    }

    /**
     * Update an existing animal type. Used by the inline editor on the types
     * list (mainly to set/adjust default_birth_value for the natural-increase
     * P&L line).
     */
    public function updateType(Request $request, $id)
    {
        $type = FarmAnimalType::where('id', $id)->where('deleted', '!=', 1)->firstOrFail();

        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:farm_animal_types,name,' . $type->id,
            'description' => 'nullable|string',
            'default_birth_value' => 'nullable|numeric|min:0',
        ]);

        $type->fill($request->only(['name', 'description', 'default_birth_value']));
        $type->save();

        return response()->json($type);
    }

    /** Get a single animal type for the edit form. */
    public function showType($id)
    {
        $type = FarmAnimalType::where('id', $id)->where('deleted', '!=', 1)->firstOrFail();
        return response()->json($type);
    }

    /** Soft-delete an animal type. Refuses if any non-deleted animals still reference it. */
    public function destroyType($id)
    {
        $type = FarmAnimalType::where('id', $id)->where('deleted', '!=', 1)->firstOrFail();

        $inUse = FarmAnimal::where('animal_type_id', $type->id)->where('deleted', '!=', 1)->exists();
        if ($inUse) {
            return response()->json([
                'status' => 'error',
                'message' => 'This type is still used by one or more animals. Reassign or remove them first.',
            ], 422);
        }

        $type->deleted = 1;
        $type->save();

        return response()->json(['status' => 'success', 'message' => 'Animal type deleted']);
    }

    /** Get a single breed for the edit form. */
    public function showBreed($id)
    {
        $breed = AnimalBreed::with('animalType:id,name')->findOrFail($id);
        return response()->json($breed);
    }

    /** Update breed name / description / type. */
    public function updateBreed(Request $request, $id)
    {
        $breed = AnimalBreed::findOrFail($id);

        $request->validate([
            'animal_type_id' => 'sometimes|required|integer|exists:farm_animal_types,id',
            'breed_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $breed->fill($request->only(['animal_type_id', 'breed_name', 'description']));
        $breed->save();

        return response()->json($breed);
    }

    /** Hard-delete a breed (no soft-delete column on this table). */
    public function destroyBreed($id)
    {
        $breed = AnimalBreed::findOrFail($id);

        $inUse = FarmAnimal::where('breed_id', $breed->id)->where('deleted', '!=', 1)->exists();
        if ($inUse) {
            return response()->json([
                'status' => 'error',
                'message' => 'This breed is still used by one or more animals. Reassign or remove them first.',
            ], 422);
        }

        $breed->delete();

        return response()->json(['status' => 'success', 'message' => 'Breed deleted']);
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

        $accountId = $this->resolveAccountId($request);

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

    public function sell(Request $request, FarmAnimal $animal)
    {
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID')
            ?? optional($request->user())->account_id;

        if ($accountId && $animal->account_id != $accountId) {
            abort(403, 'Unauthorized access to this animal');
        }

        $request->validate([
            'sale_price' => 'required|numeric|min:0',
            'sale_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        // Update animal status to sold
        $oldValues = $animal->getOriginal();
        $animal->update(['status' => 'sold']);

        // Create income event
        $event = FarmAnimalEvent::create([
            'account_id' => $animal->account_id,
            'farm_id' => $animal->farm_id,
            'animal_id' => $animal->id,
            'event_type' => 'Sold',
            'event_date' => $request->sale_date,
            'cost' => $request->sale_price,
            'cost_type' => 'income',
            'meta' => json_encode(['notes' => $request->notes ?? "Animal sold for R{$request->sale_price}"]),
        ]);

        AuditLogService::logUpdate($animal, $oldValues, $request,
            "Sold animal #{$animal->animal_tag} for R{$request->sale_price}");

        return response()->json([
            'status' => 'success',
            'message' => "Animal sold for R{$request->sale_price}",
            'event' => $event,
        ]);
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