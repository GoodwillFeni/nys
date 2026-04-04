<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalEvent;
use App\Services\AuditLogService;

class AnimalEventController extends Controller
{
    //SINGLE EVENT
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
        ]);

        //AUDIT LOG
        AuditLogService::logCreate(
            $event,
            $req,
            "Created animal event ({$event->event_type}) for animal ID {$event->animal_id}"
        );

        return response()->json($event);
    }

    // BULK EVENT
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
                $q->whereHas('animalType', function ($q2) use ($req) {
                    $q2->where('name', $req->animal_type);
                });
            })
            ->where('deleted', 0)
            ->get();

        $rows = [];
        $totalCost = $req->cost ?? 0;
        $animalCount = $animals->count();
        $costPerAnimal = $animalCount > 0 ? round($totalCost / $animalCount, 2) : 0;

        foreach ($animals as $animal) {
            $rows[] = [
                'account_id' => $req->account_id,
                'farm_id' => $req->farm_id,
                'animal_id' => $animal->id,
                'event_type' => $req->event_type,
                'event_date' => $req->event_date,
                'cost' => $costPerAnimal,
                'cost_type' => $req->cost_type ?? 'expense',
                'batch_id' => $batchId,
                'meta' => json_encode($req->meta),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        FarmAnimalEvent::insert($rows);

        //Audit log for bulk event
        AuditLogService::logCreate(
            new FarmAnimalEvent(),
            $req,
            "Bulk event '{$req->event_type}' applied to {$animalCount} animals @ R{$costPerAnimal} each (Total: R{$totalCost}, Batch: {$batchId})",
            [
                'batch_id' => $batchId,
                'animal_count' => $animalCount,
                'event_type' => $req->event_type,
                'total_cost' => $totalCost,
                'cost_per_animal' => $costPerAnimal,
            ]
        );

        return response()->json([
            'message' => "Bulk event created — R{$totalCost} divided across {$animalCount} animals (R{$costPerAnimal} each)",
            'count' => $animalCount,
            'cost_per_animal' => $costPerAnimal,
            'batch_id' => $batchId
        ]);
    }

    // LIST EVENTS
    public function list(Request $req)
    {
        $accountId = $req->account_id
            ?? $req->header('X-Account-ID');

        $query = FarmAnimalEvent::with(['animal:id,animal_tag,animal_name,animal_type_id', 'farm:id,name'])
            ->where('deleted', 0);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        if ($req->farm_id) {
            $query->where('farm_id', $req->farm_id);
        }
        if ($req->animal_id) {
            $query->where('animal_id', $req->animal_id);
        }
        if ($req->event_type) {
            $query->where('event_type', $req->event_type);
        }
        if ($req->cost_type) {
            $query->where('cost_type', $req->cost_type);
        }
        if ($req->animal_type_id) {
            $query->whereHas('animal', function ($q) use ($req) {
                $q->where('animal_type_id', $req->animal_type_id);
            });
        }
        if ($req->search) {
            $query->whereHas('animal', function ($q) use ($req) {
                $q->where('animal_tag', 'like', "%{$req->search}%")
                  ->orWhere('animal_name', 'like', "%{$req->search}%");
            });
        }

        \Log::info('EventList query', [
            'account_id' => $accountId,
            'farm_id' => $req->farm_id,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'total_in_db' => \App\Models\FarmAnimalEvent::count(),
        ]);

        $events = $query->latest('event_date')->paginate(50);

        // Cost summary for the filtered set (mirror the same filters)
        $summaryQuery = FarmAnimalEvent::where('deleted', 0);
        if ($accountId) $summaryQuery->where('account_id', $accountId);
        if ($req->farm_id) $summaryQuery->where('farm_id', $req->farm_id);
        if ($req->animal_id) $summaryQuery->where('animal_id', $req->animal_id);
        if ($req->event_type) $summaryQuery->where('event_type', $req->event_type);
        if ($req->cost_type) $summaryQuery->where('cost_type', $req->cost_type);
        if ($req->animal_type_id) {
            $summaryQuery->whereHas('animal', function ($q) use ($req) {
                $q->where('animal_type_id', $req->animal_type_id);
            });
        }
        if ($req->search) {
            $summaryQuery->whereHas('animal', function ($q) use ($req) {
                $q->where('animal_tag', 'like', "%{$req->search}%")
                  ->orWhere('animal_name', 'like', "%{$req->search}%");
            });
        }

        $summary = $summaryQuery
            ->selectRaw("
                SUM(CASE WHEN cost_type = 'income' THEN cost ELSE 0 END) as income,
                SUM(CASE WHEN cost_type = 'expense' THEN cost ELSE 0 END) as expense,
                SUM(CASE WHEN cost_type = 'running' THEN cost ELSE 0 END) as running,
                SUM(CASE WHEN cost_type = 'loss' THEN cost ELSE 0 END) as loss,
                SUM(CASE WHEN cost_type = 'birth' THEN cost ELSE 0 END) as birth,
                COUNT(*) as total_events
            ")
            ->first();

        return response()->json([
            'events' => $events,
            'summary' => $summary,
        ]);
    }

    // DASHBOARD
    public function dashboard(Request $req)
    {
        $data = [
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

        return $data;
    }

    // UPDATE EVENT
    public function update(Request $req, $id)
    {
        $event = FarmAnimalEvent::where('deleted', 0)->findOrFail($id);

        $req->validate([
            'event_type' => 'sometimes|required',
            'event_date' => 'sometimes|required|date',
            'cost' => 'sometimes|numeric',
            'cost_type' => 'sometimes|in:income,expense,loss,running,birth',
        ]);

        $oldValues = $event->getOriginal();

        $event->update($req->only([
            'event_type', 'event_date', 'cost', 'cost_type', 'meta'
        ]));

        AuditLogService::logUpdate($event, $oldValues, $req,
            "Updated animal event ({$event->event_type}) ID {$event->id}");

        return response()->json($event);
    }

    // DELETE EVENT (soft delete)
    public function destroy(Request $req, $id)
    {
        $event = FarmAnimalEvent::where('deleted', 0)->findOrFail($id);

        $event->update(['deleted' => 1]);

        AuditLogService::logDelete($event, $req,
            "Deleted animal event ({$event->event_type}) ID {$event->id}");

        return response()->json(['message' => 'Event deleted successfully']);
    }
}