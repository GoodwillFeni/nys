<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopPosCart;
use App\Models\ShopPosCartItem;
use App\Models\ShopPosSale;
use App\Models\ShopPosSaleItem;
use App\Models\ShopProduct;
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
            'customer_name' => 'nullable|string|max:255',
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

            $sale = ShopPosSale::create([
                'account_id' => $accountId,
                'cashier_user_id' => $userId,
                'customer_name' => $request->customer_name,
                'total_amount' => $totalAmount,
                'total_profit' => $totalProfit,
                'sale_datetime' => now(),
            ]);

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
            $cart->customer_name = $request->customer_name;
            $cart->save();

            $cart->items()->delete();

            return response()->json([
                'status' => 'success',
                'data' => $sale->load('items'),
            ]);
        });
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

        $sales = ShopPosSale::with('items')
            ->where('account_id', $accountId)
            ->whereBetween('sale_datetime', [$request->from, $request->to])
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
            $item->prof_per_product = (float) $request->prof_per_product;
            $item->save();

            $sale = $item->sale;
            $sale->load('items');

            $totalProfit = 0;
            foreach ($sale->items as $saleItem) {
                $totalProfit += ((float) $saleItem->prof_per_product * (int) $saleItem->qty_sold);
            }

            $sale->total_profit = $totalProfit;
            $sale->save();

            return response()->json([
                'status' => 'success',
                'data' => $sale->load('items'),
            ]);
        });
    }
}
