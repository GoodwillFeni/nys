<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmAnimal;
use App\Models\AnimalEvent;


class AnimalEventController extends Controller
{
    public function store(Request $request, FarmAnimal $animal)
    {
        if ($animal->account_id != $request->account_id) {
            abort(403);
        }

        $request->validate([
            'event_type' => 'required|string',
            'event_date' => 'required|date'
        ]);

        $event = AnimalEvent::create([
            'account_id' => $request->account_id,
            'farm_id' => $animal->farm_id,
            'animal_id' => $animal->id,
            'event_type' => $request->event_type,
            'event_date' => $request->event_date,
            'meta' => $request->meta,
            'created_by_user_id' => $request->user()->id
        ]);

        // Auto-update status for sale/death
        if ($request->event_type === 'sold') {
            $animal->update(['status' => 'sold']);
        }

        if ($request->event_type === 'died') {
            $animal->update(['status' => 'dead']);
        }

        return $event;
    }
}
