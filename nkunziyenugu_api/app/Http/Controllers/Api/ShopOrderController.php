<?php

namespace App\Http\Controllers\Api;

use App\Mail\OrderApproved;
use App\Mail\OrderPlaced;
use App\Mail\OrderQuestion;
use App\Mail\OrderRejected;
use App\Models\ShopCashflow;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ShopOrderController extends ShopBaseController
{
    // ── Customer: view own orders ─────────────────────────────────────────────

    public function myOrders(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        $orders = ShopOrder::with('items.product')
            ->where('account_id', $accountId)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    // ── Customer: place order ─────────────────────────────────────────────────

    public function createOrder(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:shop_products,id',
            'items.*.qty'    => 'required|integer|min:1',
            'notes'          => 'nullable|string|max:500',
            'payment_method' => 'required|in:pay_in_store,deposit,credit',
            'payment_proof'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $userId    = $request->user()->id;
        $proofPath = null;

        // Store deposit slip if provided
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')
                ->store('deposits', 'public');
        }

        return DB::transaction(function () use ($request, $accountId, $userId, $proofPath) {
            $order = ShopOrder::create([
                'account_id'        => $accountId,
                'user_id'           => $userId,
                'status'            => ShopOrder::STATUS_PENDING_APPROVAL,
                'total_amount'      => 0,
                'notes'             => $request->notes,
                'payment_method'    => $request->payment_method,
                'payment_proof_path' => $proofPath,
            ]);

            $total = 0;

            foreach ($request->items as $item) {
                $product = ShopProduct::lockForUpdate()
                    ->where('id', $item['product_id'])
                    ->where('account_id', $accountId)
                    ->where('deleted', false)
                    ->first();

                if (!$product) {
                    throw new \RuntimeException('Product not found');
                }

                // Stock check only — do NOT deduct yet (deducted on approval)
                $qty = (int) $item['qty'];
                if ($product->stock_level < $qty) {
                    throw new \RuntimeException("Insufficient stock for {$product->product_name}");
                }

                $unitPrice = (float) $product->actual_price;
                $lineTotal = $unitPrice * $qty;

                ShopOrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $product->id,
                    'qty'         => $qty,
                    'unit_price'  => $unitPrice,
                    'total_price' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $order->total_amount = $total;
            $order->save();

            // Send confirmation email
            $user = $request->user();
            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new OrderPlaced($order->load('items.product', 'user')));
                } catch (\Throwable $e) {
                    // Log but don't fail the order
                    \Log::warning('OrderPlaced email failed: ' . $e->getMessage());
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Order placed successfully. Awaiting approval.',
                'data'    => $order->load('items.product'),
            ], 201);
        });
    }

    // ── Admin: list all orders ────────────────────────────────────────────────

    public function adminIndex(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $query = ShopOrder::with('items.product', 'user', 'approvedBy')
            ->where('account_id', $accountId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    // ── Admin: update order (approve/reject/edit) ─────────────────────────────

    public function adminUpdate(Request $request, ShopOrder $order)
    {
        $accountId = $this->requireActiveAccountId($request);
        if ($order->account_id != $accountId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'status'           => ['nullable', Rule::in(ShopOrder::adminTransitionStatuses())],
            'rejection_reason' => 'nullable|string|max:500',
            'notes'            => 'nullable|string|max:500',
            'mark_paid'        => 'nullable|boolean',
            'items'            => 'nullable|array',
            'items.*.id'       => 'required|exists:shop_order_items,id',
            'items.*.qty'      => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $order) {
            $previousStatus = $order->status;
            $newStatus      = $request->input('status', $previousStatus);

            // Update notes
            if ($request->filled('notes')) {
                $order->notes = $request->notes;
            }

            // Approve
            if ($newStatus === ShopOrder::STATUS_APPROVED && $previousStatus !== ShopOrder::STATUS_APPROVED) {
                $order->status              = ShopOrder::STATUS_APPROVED;
                $order->approved_by_user_id = $request->user()->id;
                $order->approved_at         = now();
                $order->rejection_reason    = null;

                // Deduct stock now
                foreach ($order->items as $item) {
                    $product = ShopProduct::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->stock_level = max(0, (int) $product->stock_level - $item->qty);
                        $product->save();
                    }
                }

                // Deposit orders: payment is already received (deposit slip uploaded).
                // Record it in cashflow on approval so it appears in the Cash Flow view.
                if ($order->payment_method === 'deposit') {
                    ShopCashflow::create([
                        'account_id'       => $order->account_id,
                        'user_id'          => $request->user()->id,
                        'transaction_type' => 'Income',
                        'payment_type'     => 'EFT',
                        'amount'           => $order->total_amount,
                        'date'             => now()->toDateString(),
                        'notes'            => 'Online Order #' . $order->id . ' (Deposit)',
                        'deleted'          => false,
                    ]);
                }

                // Email customer
                $order->load('items.product', 'user');
                if ($order->user?->email) {
                    try {
                        Mail::to($order->user->email)->send(new OrderApproved($order));
                    } catch (\Throwable $e) {
                        \Log::warning('OrderApproved email failed: ' . $e->getMessage());
                    }
                }
            }

            // Reject
            if ($newStatus === ShopOrder::STATUS_REJECTED && $previousStatus !== ShopOrder::STATUS_REJECTED) {
                $order->status           = ShopOrder::STATUS_REJECTED;
                $order->rejection_reason = $request->rejection_reason;

                $order->load('items.product', 'user');
                if ($order->user?->email) {
                    try {
                        Mail::to($order->user->email)->send(new OrderRejected($order));
                    } catch (\Throwable $e) {
                        \Log::warning('OrderRejected email failed: ' . $e->getMessage());
                    }
                }
            }

            // Mark completed
            if ($newStatus === ShopOrder::STATUS_COMPLETED && $previousStatus !== ShopOrder::STATUS_COMPLETED) {
                $order->status = ShopOrder::STATUS_COMPLETED;

                // Deposit + pending_approval → completed in one step (Approve & Complete).
                // Must deduct stock and record cashflow here since approval step was skipped.
                if ($order->payment_method === 'deposit' && $previousStatus === ShopOrder::STATUS_PENDING_APPROVAL) {
                    $order->approved_by_user_id = $request->user()->id;
                    $order->approved_at         = now();
                    $order->rejection_reason    = null;

                    foreach ($order->items as $item) {
                        $product = ShopProduct::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $product->stock_level = max(0, (int) $product->stock_level - $item->qty);
                            $product->save();
                        }
                    }

                    ShopCashflow::create([
                        'account_id'       => $order->account_id,
                        'user_id'          => $request->user()->id,
                        'transaction_type' => 'Income',
                        'payment_type'     => 'EFT',
                        'amount'           => $order->total_amount,
                        'date'             => now()->toDateString(),
                        'notes'            => 'Online Order #' . $order->id . ' (Deposit — Approved & Completed)',
                        'deleted'          => false,
                    ]);

                    // Notify customer
                    $order->load('items.product', 'user');
                    if ($order->user?->email) {
                        try {
                            Mail::to($order->user->email)->send(new OrderApproved($order));
                        } catch (\Throwable $e) {
                            \Log::warning('OrderApproved email failed: ' . $e->getMessage());
                        }
                    }
                }

                // Pay-in-store orders: customer pays on collection. Record income now.
                if ($order->payment_method === 'pay_in_store') {
                    ShopCashflow::create([
                        'account_id'       => $order->account_id,
                        'user_id'          => $request->user()->id,
                        'transaction_type' => 'Income',
                        'payment_type'     => 'Cash',
                        'amount'           => $order->total_amount,
                        'date'             => now()->toDateString(),
                        'notes'            => 'Online Order #' . $order->id . ' (Pay in Store)',
                        'deleted'          => false,
                    ]);
                }
            }

            // Mark credit order as paid — record cashflow income so it appears in Cash Flow view
            if ($request->boolean('mark_paid') && $order->payment_method === 'credit' && !$order->paid_at) {
                $order->paid_at = now();

                ShopCashflow::create([
                    'account_id'       => $order->account_id,
                    'user_id'          => $request->user()->id,
                    'transaction_type' => 'Income',
                    'payment_type'     => 'Credit',
                    'amount'           => $order->total_amount,
                    'date'             => now()->toDateString(),
                    'notes'            => 'Online Order #' . $order->id . ' (Credit — Payment Received)',
                    'deleted'          => false,
                ]);
            }

            // Update item quantities (only before approval)
            if ($request->filled('items') && $previousStatus === ShopOrder::STATUS_PENDING_APPROVAL) {
                $newTotal = 0;
                foreach ($request->items as $itemData) {
                    $item = ShopOrderItem::find($itemData['id']);
                    if ($item && $item->order_id === $order->id) {
                        $item->qty         = (int) $itemData['qty'];
                        $item->total_price = $item->unit_price * $item->qty;
                        $item->save();
                    }
                }
                $newTotal = $order->fresh()->items->sum('total_price');
                $order->total_amount = $newTotal;
            }

            $order->save();

            return response()->json([
                'status' => 'success',
                'data'   => $order->load('items.product', 'user', 'approvedBy'),
            ]);
        });
    }

    // ── Admin: ask customer a question via email ──────────────────────────────

    public function askCustomer(Request $request, ShopOrder $order)
    {
        $accountId = $this->requireActiveAccountId($request);
        if ($order->account_id != $accountId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate(['message' => 'required|string|max:1000']);

        $order->load('user');
        if (!$order->user?->email) {
            return response()->json(['message' => 'Customer has no email address'], 422);
        }

        try {
            Mail::to($order->user->email)->send(new OrderQuestion($order, $request->message));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }

        return response()->json(['status' => 'success', 'message' => 'Message sent to customer']);
    }

    // ── Customer: upload deposit proof after order placed ─────────────────────

    public function uploadProof(Request $request, ShopOrder $order)
    {
        $accountId = $this->requireActiveAccountId($request);

        if ($order->account_id != $accountId || $order->user_id != $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($order->payment_proof_path) {
            Storage::disk('public')->delete($order->payment_proof_path);
        }

        $path = $request->file('payment_proof')->store('deposits', 'public');
        $order->update(['payment_proof_path' => $path]);

        return response()->json(['status' => 'success', 'path' => $path]);
    }
}
