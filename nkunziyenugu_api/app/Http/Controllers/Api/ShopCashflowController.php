<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopCashflow;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ShopCashflowController extends ShopBaseController
{
    public function index(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $rows = ShopCashflow::query()
            ->with(['user', 'updatedBy'])
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

        if ((int) $cashflow->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $cashflow->load(['user', 'updatedBy']);
        return response()->json(['status' => 'success', 'data' => $cashflow]);
    }

    public function store(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

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
            'updated_by_user_id' => null,
            'transaction_type' => $request->transaction_type,
            'payment_type' => $request->payment_type,
            'notes' => $request->notes,
            'date' => $request->date,
            'amount' => $request->amount,
        ]);

        AuditLogService::logCreate($row, $request, 'Created cashflow row');

        $row->load(['user', 'updatedBy']);

        return response()->json(['status' => 'success', 'data' => $row], 201);
    }

    public function update(Request $request, ShopCashflow $cashflow)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

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

        $oldValues = $cashflow->getAttributes();

        $cashflow->update([
            'updated_by_user_id' => $request->user()->id,
            'transaction_type' => $request->transaction_type,
            'payment_type' => $request->payment_type,
            'notes' => $request->notes,
            'date' => $request->date,
            'amount' => $request->amount,
        ]);

        AuditLogService::logUpdate($cashflow, $oldValues, $request, 'Updated cashflow row');

        $cashflow->load(['user', 'updatedBy']);

        return response()->json(['status' => 'success', 'data' => $cashflow]);
    }

    public function destroy(Request $request, ShopCashflow $cashflow)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if ((int) $cashflow->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $oldValues = $cashflow->getAttributes();
        $cashflow->deleted = true;
        $cashflow->updated_by_user_id = $request->user()->id;
        $cashflow->save();

        AuditLogService::logDelete($cashflow, $request, 'Deleted cashflow row');

        return response()->json(['status' => 'success', 'message' => 'Deleted']);
    }
}
