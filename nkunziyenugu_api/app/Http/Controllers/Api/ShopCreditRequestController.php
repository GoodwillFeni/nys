<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopCreditRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ShopCreditRequestController extends ShopBaseController
{
    public function index(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        $status = $request->get('status');

        $q = ShopCreditRequest::with(['customer', 'reviewedBy'])
            ->where('account_id', $accountId);

        if ($status) {
            $q->where('status', $status);
        }

        $rows = $q->orderByDesc('created_at')->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function approve(Request $request, ShopCreditRequest $creditRequest)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        if ((int) $creditRequest->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        if ((string) $creditRequest->status !== 'Pending') {
            return response()->json(['status' => 'error', 'message' => 'Request is already processed'], 400);
        }

        $request->validate([
            'review_notes' => 'nullable|string',
        ]);

        $old = $creditRequest->getAttributes();

        $creditRequest->status = 'Approved';
        $creditRequest->reviewed_by_user_id = $request->user()->id;
        $creditRequest->reviewed_at = now();
        $creditRequest->review_notes = $request->review_notes;
        $creditRequest->save();

        AuditLogService::logUpdate($creditRequest, $old, $request, 'Approved credit request');

        return response()->json(['status' => 'success', 'data' => $creditRequest->fresh()->load(['customer', 'reviewedBy'])]);
    }

    public function decline(Request $request, ShopCreditRequest $creditRequest)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        if ((int) $creditRequest->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        if ((string) $creditRequest->status !== 'Pending') {
            return response()->json(['status' => 'error', 'message' => 'Request is already processed'], 400);
        }

        $request->validate([
            'review_notes' => 'nullable|string',
        ]);

        $old = $creditRequest->getAttributes();

        $creditRequest->status = 'Declined';
        $creditRequest->reviewed_by_user_id = $request->user()->id;
        $creditRequest->reviewed_at = now();
        $creditRequest->review_notes = $request->review_notes;
        $creditRequest->save();

        AuditLogService::logUpdate($creditRequest, $old, $request, 'Declined credit request');

        return response()->json(['status' => 'success', 'data' => $creditRequest->fresh()->load(['customer', 'reviewedBy'])]);
    }
}
