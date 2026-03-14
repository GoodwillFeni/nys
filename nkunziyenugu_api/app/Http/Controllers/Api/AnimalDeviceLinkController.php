<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AnimalDeviceLink;
use App\Models\FarmAnimal;
use App\Models\Device;
use App\Services\AuditLogService;

class AnimalDeviceLinkController extends Controller
{
    // Link a device to an animal
    public function linkDevice(Request $request)
    {
        $request->validate([
            'animal_id' => 'required|integer|exists:farm_animals,id',
            'device_id' => 'required|integer|exists:devices,id',
        ]);

        $animal = FarmAnimal::find($request->animal_id);
        
        // Verify account ownership
        // if ($animal->account_id != $request->user()->account_id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Check if link already exists and is active
        // $existingLink = AnimalDeviceLink::where('animal_id', $request->animal_id)
        //     ->where('device_id', $request->device_id)
        //     ->where('deleted', 0)
        //     ->first();

        // if ($existingLink) {
        //     return response()->json(['message' => 'Device already linked to this animal'], 202);
        // }

        //Check if device is already linked to another animal
        $existingDeviceLink = AnimalDeviceLink::where('device_id', $request->device_id)
            ->where('deleted', 0)
            ->first();

        if ($existingDeviceLink) {
            return response()->json([
                'message' => 'Device already linked to another animal or assert. Do you want to transfer the device?',
                'data' => $existingDeviceLink
            ], 409); // 
        }

        $link = AnimalDeviceLink::create([
            'account_id' => $animal->account_id,
            'animal_id' => $request->animal_id,
            'device_id' => $request->device_id,
            'linked_from' => now(),
            'deleted' => 0,
        ]);

        return response()->json([
            'message' => 'Device linked to animal successfully',
            'data' => $link
        ], 201);

        AuditLogService::logCreate($link, $request, "Linked device to animal: {$animal->animal_name}");
    }

    //Transfer a device from one animal to another
    public function transferDevice(Request $request)
    {
        $request->validate([
            'animal_id' => 'required|integer|exists:farm_animals,id',
            'device_id' => 'required|integer|exists:devices,id',
        ]);

        // $user = $request->user();

        // $animal = FarmAnimal::find($request->animal_id);
        // if ($animal->account_id != $user->account_id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Find existing active link for the device
        $existingDeviceLink = AnimalDeviceLink::where('device_id', $request->device_id)
            ->where('deleted', 0)
            ->first();

        if ($existingDeviceLink) {
            // Get the primary key of that link
            $existingId = $existingDeviceLink->id;

            // Update the record using the primary key
            AnimalDeviceLink::where('id', $existingId)->update([
                'deleted' => 1,
                'linked_to' => now(),
            ]);
        }

        $link = AnimalDeviceLink::create([
            'account_id' => $request->account_id,
            'animal_id' => $request->animal_id,
            'device_id' => $request->device_id,
            'linked_from' => now(),
            'deleted' => 0,
        ]);

        AuditLogService::logCreate($link, $request, "Transferred device to animal: {$request->animal_id}");

        return response()->json([
            'message' => 'Device transferred successfully',
            'data' => $link
        ], 201);
    }
    // Unlink a device from an animal
    public function unlinkDevice(Request $request, $linkId)
    {
        $link = AnimalDeviceLink::find($linkId);
        $animal_id = FarmAnimal::find($request->animal_id);
        if (!$link) {
            return response()->json(['message' => 'Link not found'], 404);
        }

        // Verify account ownership
        // if ($link->account_id != $request->user()->account_id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $link->update([
            'deleted' => 1,
            'linked_to' => now(),
        ]);

        return response()->json([
            'message' => 'Device unlinked successfully',
            'data' => $link
        ]);

        //Audit log
        AuditLogService::logUpdate($link, $link->getAttributes(), $request, "Unlinked device from animal: {$animal_id->animal_name}");
    }

    // Get devices linked to an animal
    public function getAnimalDevices(Request $request, $animalId)
    {
        $animal = FarmAnimal::find($animalId);

        if (!$animal) {
            return response()->json(['message' => 'Animal not found'], 404);
        }

        // Verify account ownership
        if ($animal->account_id != $request->user()->account_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $devices = AnimalDeviceLink::where('animal_id', $animalId)
            ->where('deleted', 0)
            ->with('device')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $devices
        ]);
    }

    // Get animals linked to a device
    public function getDeviceAnimals($deviceId)
    {
        $device = Device::find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $animals = AnimalDeviceLink::where('device_id', $deviceId)
            ->where('deleted', 0)
            ->with('animal')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $animals
        ]);
    }
}
