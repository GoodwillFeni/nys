<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalEvent;

class AnimalEventController extends Controller
{
    // ✅ SINGLE EVENT
    public function storeSingle(Request $req)
    {
        $req->validate([
            'account_id' => 'required',
            'farm_id' => 'required',
            'animal_id' => 'required',
            'event_type' => 'required',
            'event_date' => 'required|date',
        ]);

        $event = FarmAnimalEvent::create([
            'account_id' => $req->account_id,
            'farm_id' => $req->farm_id,
            'animal_id' => $req->animal_id,
            'event_type' => $req->event_type,
            'event_date' => $req->event_date,
            'cost' => $req->cost ?? 0,
            'cost_type' => $req->cost_type ?? 'expense',
            'meta' => $req->meta,
            'created_by_user_id' => auth()->id()
        ]);

        return response()->json($event);
    }

    // ✅ BULK EVENT
    public function storeBulk(Request $req)
    {
        $req->validate([
            'account_id' => 'required',
            'farm_id' => 'required',
            'event_type' => 'required',
            'event_date' => 'required|date',
        ]);

        $batchId = Str::uuid();

        $animals = FarmAnimal::where('farm_id', $req->farm_id)
            ->when($req->animal_type, function ($q) use ($req) {
                $q->where('type', $req->animal_type);
            })
            ->where('deleted', 0)
            ->get();

        $rows = [];

        foreach ($animals as $animal) {
            $rows[] = [
                'account_id' => $req->account_id,
                'farm_id' => $req->farm_id,
                'animal_id' => $animal->id,
                'event_type' => $req->event_type,
                'event_date' => $req->event_date,
                'cost' => $req->cost ?? 0,
                'cost_type' => $req->cost_type ?? 'expense',
                'batch_id' => $batchId,
                'meta' => json_encode($req->meta),
                'created_by_user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        FarmAnimalEvent::insert($rows);

        return response()->json([
            'message' => 'Bulk event created',
            'count' => count($rows),
            'batch_id' => $batchId
        ]);
    }

    // ✅ LIST EVENTS
    public function list()
    {
        return FarmAnimalEvent::where('deleted', 0)
            ->latest()
            ->limit(100)
            ->get();
    }

    // ✅ DASHBOARD
    public function dashboard()
    {
        return [
            'income' => FarmAnimalEvent::where('deleted', 0)
                ->where('cost_type', 'income')
                ->sum('cost'),

            'expenses' => FarmAnimalEvent::where('deleted', 0)
                ->whereIn('cost_type', ['expense','running','loss'])
                ->sum('cost'),

            'by_event' => FarmAnimalEvent::where('deleted', 0)
                ->selectRaw('event_type, SUM(cost) as total')
                ->groupBy('event_type')
                ->get()
        ];
    }
}