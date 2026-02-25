<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Farm;

class FarmController extends Controller
{
    public function index(Request $request)
    {
        $accountId = (int) $request->header('X-Account-ID');
        return Farm::where('account_id', $accountId)
                   ->where('deleted', '!=', 1)
                   ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string'
        ]);

        return Farm::create([
            'account_id' => $request->account_id,
            'name' => $request->name,
            'location' => $request->location
        ]);
    }

    public function update(Request $request, Farm $farm)
    {
        $this->authorizeFarm($farm, $request->account_id);

        $farm->update($request->only('name', 'location', 'description', 'is_active'));

        return $farm;
    }

    public function destroy(Request $request, Farm $farm)
    {
        $this->authorizeFarm($farm, $request->account_id);
        $farm->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function authorizeFarm($farm, $accountId)
    {
        if ($farm->account_id != $accountId) {
            abort(403);
        }
    }
}
