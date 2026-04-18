<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ShopPosSale;
use App\Models\ShopProduct;
use App\Models\ShopCustomer;
use App\Models\ShopCashflow;
use App\Models\ShopCreditRequest;
use App\Models\ShopOrder;
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

        // Sales by payment method — POS rows
        $posByPayment = DB::table('shop_pos_sales')
            ->where('account_id', $accountId)
            ->whereDate('sale_datetime', '>=', $monthStart)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        // Sales by payment method — paid online orders
        $onlineByPayment = DB::table('shop_orders')
            ->where('account_id', $accountId)
            ->whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $today)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('payment_method', 'deposit')
                       ->whereIn('status', ['approved', 'completed']);
                })->orWhere(function ($q2) {
                    $q2->where('payment_method', 'pay_in_store')
                       ->where('status', 'completed');
                })->orWhere(function ($q2) {
                    $q2->where('payment_method', 'credit')
                       ->whereNotNull('paid_at');
                });
            })
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        // Merge the two collections by payment_method key
        $paymentMap = [];
        foreach ($posByPayment as $row) {
            $key = $row->payment_method ?? 'Unknown';
            $paymentMap[$key] = ['payment_method' => $key, 'count' => $row->count, 'total' => $row->total];
        }
        foreach ($onlineByPayment as $row) {
            $key = $row->payment_method ?? 'Unknown';
            if (isset($paymentMap[$key])) {
                $paymentMap[$key]['count'] += $row->count;
                $paymentMap[$key]['total'] += $row->total;
            } else {
                $paymentMap[$key] = ['payment_method' => $key, 'count' => $row->count, 'total' => $row->total];
            }
        }
        $salesByPayment = array_values($paymentMap);

        // Monthly trend (last 6 months) — POS sales
        $trendFrom = now()->subMonths(6)->startOfMonth()->toDateTimeString();

        $posTrend = DB::table('shop_pos_sales')
            ->where('account_id', $accountId)
            ->where('sale_datetime', '>=', $trendFrom)
            ->selectRaw("DATE_FORMAT(sale_datetime, '%Y-%m') as month, SUM(total_amount) as revenue, SUM(total_profit) as profit")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Monthly trend — paid online orders (profit approximated as 0; exact online profit is expensive to join per month)
        $onlineTrend = DB::table('shop_orders')
            ->where('account_id', $accountId)
            ->where('created_at', '>=', $trendFrom)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('payment_method', 'deposit')
                       ->whereIn('status', ['approved', 'completed']);
                })->orWhere(function ($q2) {
                    $q2->where('payment_method', 'pay_in_store')
                       ->where('status', 'completed');
                })->orWhere(function ($q2) {
                    $q2->where('payment_method', 'credit')
                       ->whereNotNull('paid_at');
                });
            })
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as revenue")
            ->groupBy('month')
            ->get();

        // Merge online revenue into POS trend
        foreach ($onlineTrend as $row) {
            if (isset($posTrend[$row->month])) {
                $posTrend[$row->month]->revenue += $row->revenue;
            } else {
                $posTrend[$row->month] = (object) ['month' => $row->month, 'revenue' => $row->revenue, 'profit' => 0];
            }
        }

        $monthlyTrend = collect($posTrend)->values()->sortBy('month')->values();

        // Cashflow this month
        $cashflowQuery = ShopCashflow::where('account_id', $accountId)
            ->where('deleted', false)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $today);

        $cashIn = (clone $cashflowQuery)->whereIn('transaction_type', ['Income', 'Cashup'])->sum('amount');
        $cashOut = (clone $cashflowQuery)->where('transaction_type', 'Expense')->sum('amount');

        // Pending credit requests (credit applications)
        $pendingCredits = ShopCreditRequest::where('account_id', $accountId)
            ->where('status', 'pending')
            ->count();

        // Online orders this month — revenue counted only when payment is actually received:
        //   deposit     → approved or completed (deposit slip verified = money in hand)
        //   pay_in_store → completed only (customer pays on collection, not at approval)
        //   credit      → only when paid_at is set (approved ≠ paid for credit)
        $onlinePaidOrders = ShopOrder::where('account_id', $accountId)
            ->whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $today)
            ->where(function ($q) {
                // deposit: payment verified at approval
                $q->where(function ($q2) {
                    $q2->where('payment_method', 'deposit')
                       ->whereIn('status', [ShopOrder::STATUS_APPROVED, ShopOrder::STATUS_COMPLETED]);
                })
                // pay_in_store: cash received only when customer collects
                ->orWhere(function ($q2) {
                    $q2->where('payment_method', 'pay_in_store')
                       ->where('status', ShopOrder::STATUS_COMPLETED);
                })
                // credit: counted only once the customer has actually paid
                ->orWhere(function ($q2) {
                    $q2->where('payment_method', 'credit')
                       ->whereNotNull('paid_at');
                });
            });

        $onlineOrdersCount   = $onlinePaidOrders->count();
        $onlineOrdersRevenue = round($onlinePaidOrders->sum('total_amount'), 2);

        // Online profit: SUM(order_items.qty × products.prof_per_product) for paid orders
        $paidOrderIds = (clone $onlinePaidOrders)->pluck('id');
        $onlineOrdersProfit = round(
            \DB::table('shop_order_items')
                ->join('shop_products', 'shop_order_items.product_id', '=', 'shop_products.id')
                ->whereIn('shop_order_items.order_id', $paidOrderIds)
                ->sum(\DB::raw('shop_order_items.qty * shop_products.prof_per_product')),
            2
        );

        // Pending online orders (waiting for approval)
        $pendingOnlineOrders = ShopOrder::where('account_id', $accountId)
            ->where('status', ShopOrder::STATUS_PENDING_APPROVAL)
            ->count();

        // Credit orders: approved but not yet paid
        $pendingCreditOrders = ShopOrder::where('account_id', $accountId)
            ->whereIn('status', [ShopOrder::STATUS_APPROVED, ShopOrder::STATUS_COMPLETED])
            ->where('payment_method', 'credit')
            ->whereNull('paid_at')
            ->selectRaw('COUNT(*) as count, SUM(total_amount) as total')
            ->first();

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
            'total_products'      => $totalProducts,
            'low_stock_products'  => $lowStockProducts,
            'total_customers'     => $totalCustomers,
            'pending_credits'     => $pendingCredits,

            // POS in-store sales
            'month_sales' => [
                'count'   => $salesCount,
                'revenue' => round($totalSalesAmount, 2),
                'profit'  => round($totalProfit, 2),
                'unpaid'  => $unpaidCount,
            ],

            // Online customer orders (paid only — credit excluded until paid)
            'online_orders' => [
                'count'            => $onlineOrdersCount,
                'revenue'          => $onlineOrdersRevenue,
                'profit'           => $onlineOrdersProfit,
                'pending_approval' => $pendingOnlineOrders,
            ],

            // Credit orders approved but awaiting payment — NOT in revenue
            'pending_credit_orders' => [
                'count' => (int) ($pendingCreditOrders->count ?? 0),
                'total' => round((float) ($pendingCreditOrders->total ?? 0), 2),
            ],

            'cashflow' => [
                'cash_in'  => round($cashIn, 2),
                'cash_out' => round($cashOut, 2),
                'net'      => round($cashIn - $cashOut, 2),
            ],
            'sales_by_payment' => $salesByPayment,
            'monthly_trend'    => $monthlyTrend,
            'top_products'     => $topProducts,
            'recent_sales'     => $recentSales,
        ]);
    }
}
