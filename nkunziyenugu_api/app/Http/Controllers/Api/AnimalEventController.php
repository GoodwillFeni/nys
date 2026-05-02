<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Birth-only: list of new offspring to auto-create. Optional.
            'offspring' => 'nullable|array',
            // Tag is optional — newborns often aren't tagged at birth. Auto-generated below.
            'offspring.*.animal_tag' => 'nullable|string|max:50',
            'offspring.*.sex' => 'required_with:offspring|string|in:Male,Female,Unknown',
            'offspring.*.breed_id' => 'nullable|integer|exists:farm_animal_breeds,id',
            'offspring.*.animal_name' => 'nullable|string|max:150',
            'offspring.*.notes' => 'nullable|string',
        ]);

        // Birth is identified by cost_type='birth' (not event_type text — users
        // type things like "New Female" or "New calf" in event_type, but the
        // dropdown they pick is what tells us this is a natural-increase entry).
        $isBirth = strtolower((string) $req->input('cost_type', '')) === 'birth';

        if ($isBirth) {
            return $this->storeBirthEvent($req);
        }

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

    /**
     * Birth-event special path: validate the mother is Female, auto-create
     * each offspring with mother_id linked, auto-fill cost from animal type's
     * default_birth_value, and force cost_type='birth' so the natural-increase
     * P&L line picks it up correctly.
     */
    protected function storeBirthEvent(Request $req)
    {
        \Log::info('storeBirthEvent triggered', [
            'animal_id' => $req->animal_id,
            'event_type' => $req->event_type,
            'offspring_count' => count($req->input('offspring', [])),
            'cost_raw' => $req->input('cost'),
        ]);

        $mother = FarmAnimal::with('animalType')->find($req->animal_id);
        if (!$mother) {
            return response()->json(['status' => 'error', 'message' => 'Mother animal not found'], 404);
        }
        if (strcasecmp((string) $mother->sex, 'Female') !== 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Birth events can only be recorded on a Female animal. ' .
                             "Animal #{$mother->animal_tag} is sex='{$mother->sex}'.",
            ], 422);
        }

        $offspring = $req->input('offspring', []);
        if (empty($offspring)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Birth event needs at least one offspring (number born + tag + sex).',
            ], 422);
        }
        $count = count($offspring);

        // Auto-fill cost when user didn't enter one. Treat 0 as "use the
        // animal type's default" — the form initializes cost=0, so an explicit
        // zero usually means the user didn't change it.
        $rawCost = $req->input('cost');
        $userProvidedCost = ($rawCost !== null && $rawCost !== '' && (float) $rawCost > 0);
        if (!$userProvidedCost) {
            $perOffspring = (float) ($mother->animalType?->default_birth_value ?? 0);
            $cost = $perOffspring * $count;
        } else {
            $cost = (float) $rawCost;
        }

        return DB::transaction(function () use ($req, $mother, $offspring, $count, $cost) {
            $meta = $req->input('meta', []);
            if (!is_array($meta)) $meta = [];
            $meta['offspring_count'] = $count;

            $event = FarmAnimalEvent::create([
                'account_id' => $req->account_id,
                'farm_id'    => $req->farm_id,
                'animal_id'  => $req->animal_id,
                'event_type' => $req->event_type,
                'event_date' => $req->event_date,
                'cost'       => $cost,
                'cost_type'  => 'birth', // always 'birth' for natural-increase tracking
                'meta'       => $meta,
            ]);

            $created = [];
            $seq = 0;
            foreach ($offspring as $o) {
                $seq++;
                $tag = trim((string) ($o['animal_tag'] ?? ''));
                if ($tag === '') {
                    // Newborn not tagged yet — generate placeholder marker.
                    // Format "NB-{event_id}-{n}" is unique (event id is unique)
                    // and clearly signals "this animal still needs a real tag".
                    $tag = "NB-{$event->id}-{$seq}";
                }

                $created[] = FarmAnimal::create([
                    'account_id'     => $req->account_id,
                    'farm_id'        => $req->farm_id,
                    'animal_type_id' => $mother->animal_type_id,
                    'breed_id'       => $o['breed_id'] ?? $mother->breed_id,
                    'mother_id'      => $mother->id,
                    'animal_tag'     => $tag,
                    'farm_tag'       => $tag,
                    'sex'            => $o['sex'],
                    'date_of_birth'  => $req->event_date,
                    'animal_name'    => $o['animal_name'] ?? null,
                    'status'         => 'Active',  // capital A — matches enum the existing rows use
                    'notes'          => $o['notes'] ?? null,
                ]);
            }

            AuditLogService::logCreate(
                $event,
                $req,
                "Created birth event for animal #{$mother->animal_tag} (" . count($created) . ' offspring, cost R' . number_format($cost, 2) . ')'
            );

            return response()->json([
                'status'    => 'success',
                'event'     => $event,
                'offspring' => $created,
                'cost'      => $cost,
            ]);
        });
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
                SUM(CASE WHEN cost_type = 'investment' THEN cost ELSE 0 END) as investment,
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
            'cost_type' => 'sometimes|in:income,expense,loss,running,birth,investment',
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