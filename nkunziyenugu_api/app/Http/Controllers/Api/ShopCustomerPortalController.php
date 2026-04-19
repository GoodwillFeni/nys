<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopCreditRequest;
use App\Models\ShopCustomer;
use App\Models\ShopPosSale;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ShopCustomerPortalController extends ShopBaseController
{
    /**
     * Legacy gate — used to require the 'Customer' role. Now replaced by the
     * route-level permission middleware (permission:CustomerCredit,view etc.).
     * Kept as a no-op so existing call sites remain harmless; delete on the
     * next cleanup pass.
     */
    protected function requireCustomerRole(Request $request, int $accountId): void
    {
        // permission middleware already gates these routes
    }

    protected function getCustomer(Request $request, int $accountId): ShopCustomer
    {
        $userId = $request->user()->id;

        $customer = ShopCustomer::query()
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->where('deleted', false)
            ->first();

        if (!$customer) {
            abort(response()->json(['status' => 'error', 'message' => 'Customer profile not found'], 404));
        }

        return $customer;
    }

    public function me(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);
        $this->requireCustomerRole($request, $accountId);

        $customer = $this->getCustomer($request, $accountId);

        return response()->json(['status' => 'success', 'data' => $customer]);
    }

    public function credit(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);
        $this->requireCustomerRole($request, $accountId);

        $customer = $this->getCustomer($request, $accountId);

        $sales = ShopPosSale::with(['items', 'cashier'])
            ->where('account_id', $accountId)
            ->where('customer_id', $customer->id)
            ->where('payment_method', 'Credit')
            ->orderByDesc('sale_datetime')
            ->get();

        $totalCredit = 0;
        $totalPaid = 0;
        $totalOutstanding = 0;

        foreach ($sales as $s) {
            $totalCredit += (float) $s->total_amount;
            if ((bool) $s->is_paid) {
                $totalPaid += (float) ($s->paid_amount ?? $s->total_amount);
            } else {
                $totalOutstanding += (float) $s->total_amount;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer' => $customer,
                'sales' => $sales,
                'summary' => [
                    'total_credit' => $totalCredit,
                    'total_paid' => $totalPaid,
                    'total_outstanding' => $totalOutstanding,
                ],
            ],
        ]);
    }

    public function myCreditRequests(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);
        $this->requireCustomerRole($request, $accountId);

        $customer = $this->getCustomer($request, $accountId);

        $rows = ShopCreditRequest::with('reviewedBy')
            ->where('account_id', $accountId)
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function requestCredit(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);
        $this->requireCustomerRole($request, $accountId);

        $customer = $this->getCustomer($request, $accountId);

        $request->validate([
            'amount_requested' => 'required|numeric|min:1',
            'reason' => 'nullable|string',
        ]);

        $row = ShopCreditRequest::create([
            'account_id' => $accountId,
            'customer_id' => $customer->id,
            'amount_requested' => $request->amount_requested,
            'reason' => $request->reason,
            'status' => 'Pending',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ]);

        AuditLogService::logCreate($row, $request, 'Customer requested credit');

        return response()->json(['status' => 'success', 'data' => $row], 201);
    }
}
