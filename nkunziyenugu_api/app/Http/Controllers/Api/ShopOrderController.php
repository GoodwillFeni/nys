<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopOrderController extends ShopBaseController
{
    public function myOrders(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        $userId = $request->user()->id;

        $orders = ShopOrder::with('items.product')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    public function createOrder(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);
        $this->requireAccountAccess($request, $accountId);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:shop_products,id',
            'items.*.qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $userId = $request->user()->id;

        return DB::transaction(function () use ($request, $accountId, $userId) {
            $order = ShopOrder::create([
                'account_id' => $accountId,
                'user_id' => $userId,
                'status' => 'pending',
                'total_amount' => 0,
                'notes' => $request->notes,
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

                $qty = (int) $item['qty'];

                if ($product->stock_level < $qty) {
                    throw new \RuntimeException("Insufficient stock for {$product->product_name}");
                }

                $unitPrice = (float) $product->actual_price;
                $lineTotal = $unitPrice * $qty;

                ShopOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ]);

                $product->stock_level = (int) $product->stock_level - $qty;
                $product->save();

                $total += $lineTotal;
            }

            $order->total_amount = $total;
            $order->status = 'placed';
            $order->save();

            return response()->json([
                'status' => 'success',
                'data' => $order->load('items.product'),
            ], 201);
        });
    }
}
