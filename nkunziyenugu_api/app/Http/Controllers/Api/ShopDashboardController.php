<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ShopPosSale;
use App\Models\ShopProduct;
use App\Models\ShopCustomer;
use App\Models\ShopCashflow;
use App\Models\ShopCreditRequest;
use Illuminate\Support\Facades\DB;

class ShopDashboardController extends ShopBaseController
{
    public function dashboard(Request $request)
    {
        $accountId = $this->requireActiveAccountId($request);

        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        // Total products & low stock
        $products = ShopProduct::where('account_id', $accountId)->where('deleted', false);
        $totalProducts = (clone $products)->count();
        $lowStockProducts = (clone $products)->where('stock_level', '<=', 5)->count();

        // Total customers
        $totalCustomers = ShopCustomer::where('account_id', $accountId)->where('deleted', false)->count();

        // Sales this month
        $monthlySales = ShopPosSale::where('account_id', $accountId)
            ->whereDate('sale_datetime', '>=', $monthStart)
            ->whereDate('sale_datetime', '<=', $today);

        $totalSalesAmount = (clone $monthlySales)->sum('total_amount');
        $totalProfit = (clone $monthlySales)->sum('total_profit');
        $salesCount = (clone $monthlySales)->count();
        $unpaidCount = (clone $monthlySales)->where('is_paid', false)->count();

        // Sales by payment method
        $salesByPayment = ShopPosSale::where('account_id', $accountId)
            ->whereDate('sale_datetime', '>=', $monthStart)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        // Monthly sales trend (last 6 months)
        $monthlyTrend = ShopPosSale::where('account_id', $accountId)
            ->where('sale_datetime', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(sale_datetime, '%Y-%m') as month, SUM(total_amount) as revenue, SUM(total_profit) as profit, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Cashflow this month
        $cashflowQuery = ShopCashflow::where('account_id', $accountId)
            ->where('deleted', false)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $today);

        $cashIn = (clone $cashflowQuery)->where('transaction_type', 'income')->sum('amount');
        $cashOut = (clone $cashflowQuery)->where('transaction_type', 'expense')->sum('amount');

        // Pending credit requests
        $pendingCredits = ShopCreditRequest::where('account_id', $accountId)
            ->where('status', 'pending')
            ->count();

        // Top selling products (this month)
        $topProducts = DB::table('shop_pos_sale_items')
            ->join('shop_pos_sales', 'shop_pos_sale_items.pos_sale_id', '=', 'shop_pos_sales.id')
            ->join('shop_products', 'shop_pos_sale_items.product_id', '=', 'shop_products.id')
            ->where('shop_pos_sales.account_id', $accountId)
            ->whereDate('shop_pos_sales.sale_datetime', '>=', $monthStart)
            ->selectRaw('shop_products.product_name, SUM(shop_pos_sale_items.qty_sold) as total_qty, SUM(shop_pos_sale_items.total_price) as total_revenue')
            ->groupBy('shop_products.product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Recent sales (last 10)
        $recentSales = ShopPosSale::with('cashier:id,name')
            ->where('account_id', $accountId)
            ->latest('sale_datetime')
            ->limit(10)
            ->get();

        return response()->json([
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'total_customers' => $totalCustomers,
            'pending_credits' => $pendingCredits,
            'month_sales' => [
                'count' => $salesCount,
                'revenue' => round($totalSalesAmount, 2),
                'profit' => round($totalProfit, 2),
                'unpaid' => $unpaidCount,
            ],
            'cashflow' => [
                'cash_in' => round($cashIn, 2),
                'cash_out' => round($cashOut, 2),
                'net' => round($cashIn - $cashOut, 2),
            ],
            'sales_by_payment' => $salesByPayment,
            'monthly_trend' => $monthlyTrend,
            'top_products' => $topProducts,
            'recent_sales' => $recentSales,
        ]);
    }
}
