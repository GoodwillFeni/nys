<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopProduct;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopProductController extends ShopBaseController
{
    public function index(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        $products = ShopProduct::query()
            ->where('account_id', $accountId)
            ->where('deleted', false)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    public function show(Request $request, ShopProduct $product)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if ((int) $product->account_id !== $accountId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not allowed'
            ], 403);
        }

        $request->validate([
            'product_name' => 'required|string|max:255',
            'product_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'actual_price' => 'required|numeric|min:0',
            'stock_price' => 'nullable|numeric|min:0',
            'cal_price_no_prof' => 'nullable|numeric|min:0',
            'cal_price' => 'nullable|numeric|min:0',
            'prof_per_product' => 'nullable|numeric|min:0',
            'qty' => 'required|integer|min:0',
            'img' => 'nullable|file|image|max:4096',
        ]);

        $imgPath = null;
        if ($request->hasFile('img')) {
            $imgPath = $request->file('img')->store('shop/products', 'public');
        }

        $product = ShopProduct::create([
            'account_id' => $accountId,
            'product_name' => $request->product_name,
            'product_type' => $request->product_type,
            'description' => $request->description,
            'int_stock' => (int) $request->qty,
            'stock_level' => (int) $request->qty,
            'stock_price' => $request->stock_price ?? 0,
            'cal_price_no_prof' => $request->cal_price_no_prof ?? 0,
            'cal_price' => $request->cal_price ?? 0,
            'actual_price' => $request->actual_price,
            'prof_per_product' => $request->prof_per_product ?? 0,
            'img_path' => $imgPath,
        ]);

        AuditLogService::logCreate($product, $request, "Created shop product: {$product->product_name}");

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, ShopProduct $product)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if ((int) $product->account_id !== $accountId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not allowed'
            ], 403);
        }

        $request->validate([
            'product_name' => 'required|string|max:255',
            'product_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'actual_price' => 'required|numeric|min:0',
            'stock_price' => 'nullable|numeric|min:0',
            'cal_price_no_prof' => 'nullable|numeric|min:0',
            'cal_price' => 'nullable|numeric|min:0',
            'prof_per_product' => 'nullable|numeric|min:0',
            'qty' => 'required|integer|min:0',
            'int_stock' => 'nullable|integer|min:0',
            'img' => 'nullable|file|image|max:4096',
        ]);

        $oldValues = $product->getAttributes();

        if ($request->hasFile('img')) {
            if ($product->img_path) {
                Storage::disk('public')->delete($product->img_path);
            }
            $product->img_path = $request->file('img')->store('shop/products', 'public');
        }

        $product->fill([
            'product_name' => $request->product_name,
            'product_type' => $request->product_type,
            'description' => $request->description,
            'stock_level' => (int) $request->qty,
            'stock_price' => $request->stock_price ?? 0,
            'cal_price_no_prof' => $request->cal_price_no_prof ?? 0,
            'cal_price' => $request->cal_price ?? 0,
            'actual_price' => $request->actual_price,
            'prof_per_product' => $request->prof_per_product ?? 0,
        ]);

        if ($request->has('int_stock')) {
            $product->int_stock = (int) $request->int_stock;
        }

        $product->save();

        AuditLogService::logUpdate($product, $oldValues, $request, "Updated shop product: {$product->product_name}");

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    public function destroy(Request $request, ShopProduct $product)
    {
        $accountId = $this->requireActiveAccountId($request);

        $this->requireAccountAccess($request, $accountId);

        if ((int) $product->account_id !== $accountId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        if (!$this->hasPrivilegedRole($request, $accountId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not allowed'
            ], 403);
        }

        $oldValues = $product->getAttributes();
        $product->deleted = true;
        $product->save();

        AuditLogService::logDelete($product, $request, "Deleted shop product: {$product->product_name}");

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted'
        ]);
    }
}
