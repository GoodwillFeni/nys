<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmAnimal;
use Illuminate\Support\Str;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        $query = FarmAnimal::with('animalType')
            ->where('account_id', $request->account_id);

        if ($request->farm_id) {
            $query->where('farm_id', $request->farm_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('global_tag', 'like', "%{$request->search}%")
                  ->orWhere('farm_tag', 'like', "%{$request->search}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(Request $request) // Create a new animal
    {
        $request->validate([
            'farm_id' => 'required|exists:farms,id',
            'animal_type_id' => 'required|exists:animal_types,id',
            'farm_tag' => 'nullable|string'
        ]);

        return FarmAnimal::create([
            'account_id' => $request->account_id,
            'farm_id' => $request->farm_id,
            'animal_type_id' => $request->animal_type_id,
            'global_tag' => strtoupper(Str::random(10)),
            'farm_tag' => $request->farm_tag,
            'sex' => $request->sex ?? 'unknown',
            'date_of_birth' => $request->date_of_birth,
            'estimated_dob' => $request->estimated_dob ?? false
        ]);
    }

    public function show(Request $request, FarmAnimal $animal)
    {
        if ($animal->account_id != $request->account_id) {
            abort(403);
        }

        return $animal->load('animalType', 'events', 'deviceLinks');
    }
}
