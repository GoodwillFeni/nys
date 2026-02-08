<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopCashflow;
use Illuminate\Http\Request;

class ShopCashflowController extends ShopBaseController
{
    public function index(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $rows = ShopCashflow::query()
            ->where('account_id', $accountId)
            ->where('deleted', false)
            ->whereBetween('date', [$request->from, $request->to])
            ->orderByDesc('date')
            ->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function show(Request $request, ShopCashflow $cashflow)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        if ((int) $cashflow->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $cashflow]);
    }

    public function store(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'transaction_type' => 'required|string|max:100',
            'payment_type' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'date' => 'required|date',
            'amount' => 'required|numeric',
        ]);

        $row = ShopCashflow::create([
            'account_id' => $accountId,
            'user_id' => $request->user()->id,
            'transaction_type' => $request->transaction_type,
            'payment_type' => $request->payment_type,
            'notes' => $request->notes,
            'date' => $request->date,
            'amount' => $request->amount,
        ]);

        return response()->json(['status' => 'success', 'data' => $row], 201);
    }

    public function update(Request $request, ShopCashflow $cashflow)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        if ((int) $cashflow->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $request->validate([
            'transaction_type' => 'required|string|max:100',
            'payment_type' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'date' => 'required|date',
            'amount' => 'required|numeric',
        ]);

        $cashflow->update([
            'transaction_type' => $request->transaction_type,
            'payment_type' => $request->payment_type,
            'notes' => $request->notes,
            'date' => $request->date,
            'amount' => $request->amount,
        ]);

        return response()->json(['status' => 'success', 'data' => $cashflow]);
    }

    public function destroy(Request $request, ShopCashflow $cashflow)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        if ((int) $cashflow->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $cashflow->deleted = true;
        $cashflow->save();

        return response()->json(['status' => 'success', 'message' => 'Deleted']);
    }
}
