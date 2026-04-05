<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopPosCart;
use App\Models\ShopPosCartItem;
use App\Models\ShopPosSale;
use App\Models\ShopPosSaleItem;
use App\Models\ShopProduct;
use App\Models\ShopCustomer;
use App\Models\ShopCashflow;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopPosController extends ShopBaseController
{
    public function getOpenCart(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $userId = $request->user()->id;

        $cart = ShopPosCart::with('items.product')
            ->where('account_id', $accountId)
            ->where('cashier_user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$cart) {
            $cart = ShopPosCart::create([
                'account_id' => $accountId,
                'cashier_user_id' => $userId,
                'status' => 'open',
            ]);
            $cart->load('items.product');
        }

        return response()->json(['status' => 'success', 'data' => $cart]);
    }

    public function addItem(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'product_id' => 'required|exists:shop_products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $userId = $request->user()->id;

        $cart = ShopPosCart::firstOrCreate([
            'account_id' => $accountId,
            'cashier_user_id' => $userId,
            'status' => 'open',
        ]);

        $product = ShopProduct::where('id', $request->product_id)
            ->where('account_id', $accountId)
            ->where('deleted', false)
            ->first();

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        $qty = (int) $request->qty;

        $item = ShopPosCartItem::where('pos_cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->qty += $qty;
        } else {
            $item = new ShopPosCartItem([
                'pos_cart_id' => $cart->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'pre_stock_level' => (int) $product->stock_level,
                'prof_per_product' => (float) $product->prof_per_product,
            ]);
        }

        $item->unit_price = $product->actual_price;
        $item->total_price = $item->qty * $item->unit_price;
        $item->save();

        $cart->load('items.product');
        return response()->json(['status' => 'success', 'data' => $cart]);
    }

    public function updateItem(Request $request, ShopPosCartItem $item)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'qty' => 'required|integer|min:1',
        ]);

        $item->load('cart');
        if ((int) $item->cart->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Item not found'], 404);
        }

        $item->qty = (int) $request->qty;
        $item->total_price = $item->qty * $item->unit_price;
        $item->save();

        $item->cart->load('items.product');
        return response()->json(['status' => 'success', 'data' => $item->cart]);
    }

    public function removeItem(Request $request, ShopPosCartItem $item)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $item->load('cart');
        if ((int) $item->cart->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Item not found'], 404);
        }

        $cart = $item->cart;
        $item->delete();

        $cart->load('items.product');
        return response()->json(['status' => 'success', 'data' => $cart]);
    }

    public function checkout(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'payment_method' => 'required|string|in:Cash,Cash Deposit,Credit',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'amount_received' => 'nullable|numeric|min:0',
        ]);

        $userId = $request->user()->id;

        $cart = ShopPosCart::with('items.product')
            ->where('account_id', $accountId)
            ->where('cashier_user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Cart is empty'], 400);
        }

        return DB::transaction(function () use ($request, $accountId, $userId, $cart) {
            $totalAmount = 0;
            $totalProfit = 0;

            foreach ($cart->items as $item) {
                $product = $item->product;
                if (!$product || $product->deleted) {
                    throw new \RuntimeException('Product missing');
                }

                if ($product->stock_level < $item->qty) {
                    throw new \RuntimeException("Insufficient stock for {$product->product_name}");
                }

                $totalAmount += (float) $item->total_price;
                $totalProfit += ((float) $item->prof_per_product * (int) $item->qty);
            }

            $paymentMethod = (string) $request->payment_method;
            $isPaid = $paymentMethod !== 'Credit';
            $amountReceived = $request->amount_received;
            $changeAmount = null;

            $customerId = $request->customer_id !== null ? (int) $request->customer_id : null;
            $customerName = $request->customer_name;
            $customerPhone = $request->customer_phone;

            if ($paymentMethod === 'Credit') {
                if (!$customerId) {
                    return response()->json(['status' => 'error', 'message' => 'customer_id is required for Credit sales'], 422);
                }

                $customer = ShopCustomer::query()
                    ->where('id', $customerId)
                    ->where('account_id', $accountId)
                    ->where('deleted', false)
                    ->first();

                if (!$customer) {
                    return response()->json(['status' => 'error', 'message' => 'Customer not found'], 422);
                }

                $customerName = $customer->name;
                $customerPhone = $customer->phone;
            } else {
                if ($customerId) {
                    $customer = ShopCustomer::query()
                        ->where('id', $customerId)
                        ->where('account_id', $accountId)
                        ->where('deleted', false)
                        ->first();

                    if ($customer) {
                        $customerName = $customerName ?: $customer->name;
                        $customerPhone = $customerPhone ?: $customer->phone;
                    }
                }
            }

            if ($paymentMethod !== 'Credit') {
                if ($amountReceived === null) {
                    return response()->json(['status' => 'error', 'message' => 'amount_received is required for this payment method'], 422);
                }

                $amountReceived = (float) $amountReceived;
                if ($amountReceived < (float) $totalAmount) {
                    return response()->json(['status' => 'error', 'message' => 'Amount received is less than total'], 422);
                }

                $changeAmount = $amountReceived - (float) $totalAmount;
            }

            $sale = ShopPosSale::create([
                'account_id' => $accountId,
                'cashier_user_id' => $userId,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'payment_method' => $paymentMethod,
                'amount_received' => $paymentMethod !== 'Credit' ? $amountReceived : null,
                'change_amount' => $paymentMethod !== 'Credit' ? $changeAmount : null,
                'total_amount' => $totalAmount,
                'total_profit' => $totalProfit,
                'sale_datetime' => now(),
                'is_paid' => $isPaid,
                'paid_at' => $isPaid ? now() : null,
                'paid_method' => $isPaid ? $paymentMethod : null,
                'paid_amount' => $isPaid ? $totalAmount : null,
            ]);

            AuditLogService::logCreate($sale, $request, 'Created POS sale');

            foreach ($cart->items as $item) {
                $product = ShopProduct::lockForUpdate()->find($item->product_id);

                if (!$product || $product->deleted) {
                    throw new \RuntimeException('Product missing');
                }

                if ($product->stock_level < $item->qty) {
                    throw new \RuntimeException("Insufficient stock for {$product->product_name}");
                }

                ShopPosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_type' => $product->product_type,
                    'qty_sold' => $item->qty,
                    'actual_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'prof_per_product' => $item->prof_per_product,
                ]);

                $product->stock_level = (int) $product->stock_level - (int) $item->qty;
                $product->save();
            }

            $cart->status = 'checked_out';
            $cart->customer_name = $customerName;
            $cart->save();

            $cart->items()->delete();

            return response()->json([
                'status' => 'success',
                'data' => $sale->load(['items', 'cashier', 'customer']),
            ]);
        });
    }

    public function markSalePaid(Request $request, ShopPosSale $sale)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        if ((int) $sale->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $request->validate([
            'paid_method' => 'required|string|in:Cash,Cash Deposit',
        ]);

        if ((string) $sale->payment_method !== 'Credit') {
            return response()->json(['status' => 'error', 'message' => 'Only Credit sales can be marked as paid'], 400);
        }

        if ((bool) $sale->is_paid) {
            return response()->json(['status' => 'error', 'message' => 'Sale is already marked as paid'], 400);
        }

        $userId = $request->user()->id;
        $paidMethod = (string) $request->paid_method;

        return DB::transaction(function () use ($request, $sale, $accountId, $userId, $paidMethod) {
            $oldValues = $sale->getAttributes();
            $sale->is_paid = true;
            $sale->paid_at = now();
            $sale->paid_method = $paidMethod;
            $sale->paid_amount = $sale->total_amount;
            $sale->save();

            AuditLogService::logUpdate($sale, $oldValues, $request, 'Marked Credit sale as paid');

            return response()->json([
                'status' => 'success',
                'data' => $sale->fresh()->load(['items', 'cashier', 'customer']),
            ]);
        });
    }

    public function updateSale(Request $request, ShopPosSale $sale)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        if ((int) $sale->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
        ]);

        $oldValues = $sale->getAttributes();

        $sale->customer_name = $request->customer_name;
        if ($request->has('customer_phone')) {
            $sale->customer_phone = $request->customer_phone;
        }
        $sale->save();

        AuditLogService::logUpdate($sale, $oldValues, $request, 'Updated POS sale customer name');

        return response()->json(['status' => 'success', 'data' => $sale->fresh()->load(['items', 'cashier', 'customer'])]);
    }

    public function salesReport(Request $request)
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

        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->endOfDay();

        $sales = ShopPosSale::with(['items', 'cashier', 'customer'])
            ->where('account_id', $accountId)
            ->whereBetween('sale_datetime', [$from, $to])
            ->orderByDesc('sale_datetime')
            ->get();

        return response()->json(['status' => 'success', 'data' => $sales]);
    }

    public function updateSaleItem(Request $request, ShopPosSaleItem $item)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json(['status' => 'error', 'message' => 'Not allowed'], 403);
        }

        $request->validate([
            'prof_per_product' => 'required|numeric',
        ]);

        $item->load('sale.items');

        if (!$item->sale || (int) $item->sale->account_id !== $accountId) {
            return response()->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return DB::transaction(function () use ($request, $item) {
            $oldItemValues = $item->getAttributes();
            $item->prof_per_product = (float) $request->prof_per_product;
            $item->save();

            AuditLogService::logUpdate($item, $oldItemValues, $request, 'Updated POS sale item profit');

            $sale = $item->sale;
            $sale->load('items');

            $totalProfit = 0;
            foreach ($sale->items as $saleItem) {
                $totalProfit += ((float) $saleItem->prof_per_product * (int) $saleItem->qty_sold);
            }

            $oldSaleValues = $sale->getAttributes();
            $sale->total_profit = $totalProfit;
            $sale->save();

            AuditLogService::logUpdate($sale, $oldSaleValues, $request, 'Updated POS sale total profit');

            return response()->json([
                'status' => 'success',
                'data' => $sale->load(['items', 'cashier', 'customer']),
            ]);
        });
    }
}
