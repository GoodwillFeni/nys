<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Farm;
use App\Models\FarmAnimal;
use App\Models\FarmAnimalEvent;
use App\Models\FarmTransaction;
use App\Models\InventoryItem;
use App\Models\ShopPosSale;
use App\Models\ShopProduct;
use App\Models\Device;
use App\Models\AuditLog;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $accountId = $request->header('X-Account-ID');

        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        // ── Detect active modules ────────────────────────────────────────────
        $hasFarm = Farm::where('account_id', $accountId)->where('deleted', '!=', 1)->exists()
            || FarmAnimal::withoutGlobalScopes()->where('account_id', $accountId)->where('deleted', '!=', 1)->exists();

        $hasShop = ShopProduct::where('account_id', $accountId)->where('deleted', false)->exists()
            || ShopPosSale::where('account_id', $accountId)->exists();

        $hasDevices = Device::where('account_id', $accountId)->exists();

        // ── Devices ──────────────────────────────────────────────────────────
        $devices = null;
        if ($hasDevices) {
            $totalDevices = Device::where('account_id', $accountId)->count();
            $onlineDevices = Device::where('account_id', $accountId)
                ->where('last_seen_at', '>=', now()->subHour())
                ->count();

            $devices = [
                'total' => $totalDevices,
                'online' => $onlineDevices,
            ];
        }

        // ── Alerts ───────────────────────────────────────────────────────────
        $lowStockFarm = 0;
        $lowStockShop = 0;
        $unpaidSales = 0;

        if ($hasFarm) {
            $farmItems = InventoryItem::where('account_id', $accountId)->where('deleted', 0)->get();
            $lowStockFarm = $farmItems->filter(fn($i) => $i->low_stock)->count();
        }

        if ($hasShop) {
            $lowStockShop = ShopProduct::where('account_id', $accountId)
                ->where('deleted', false)
                ->where('stock_level', '<=', 5)
                ->count();

            $unpaidSales = ShopPosSale::where('account_id', $accountId)
                ->where('is_paid', false)
                ->count();
        }

        $alerts = $lowStockFarm + $lowStockShop + $unpaidSales;

        // ── Activity today ───────────────────────────────────────────────────
        $activityToday = AuditLog::where('account_id', $accountId)
            ->whereDate('created_at', $today)
            ->count();

        // ── Farm summary ─────────────────────────────────────────────────────
        $farm = null;
        if ($hasFarm) {
            $totalAnimals = FarmAnimal::withoutGlobalScopes()
                ->where('account_id', $accountId)
                ->where('deleted', '!=', 1)
                ->where('status', 'active')
                ->count();

            $eventsThisMonth = FarmAnimalEvent::where('account_id', $accountId)
                ->where('deleted', 0)
                ->whereBetween('event_date', [$monthStart, $today])
                ->count();

            $eventPnl = FarmAnimalEvent::where('account_id', $accountId)
                ->where('deleted', 0)
                ->whereBetween('event_date', [$monthStart, $today])
                ->selectRaw("
                    SUM(CASE WHEN cost_type IN ('income','birth') THEN cost ELSE 0 END) as income,
                    SUM(CASE WHEN cost_type IN ('expense','running') THEN cost ELSE 0 END) as expense,
                    SUM(CASE WHEN cost_type = 'loss' THEN cost ELSE 0 END) as loss,
                    SUM(CASE WHEN cost_type = 'investment' THEN cost ELSE 0 END) as investment
                ")
                ->first();

            $txPnl = FarmTransaction::where('account_id', $accountId)
                ->where('deleted', 0)
                ->whereBetween('transaction_date', [$monthStart, $today])
                ->selectRaw("
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                ")
                ->first();

            $farmIncome = round(($eventPnl->income ?? 0) + ($txPnl->income ?? 0), 2);
            $farmExpense = round(($eventPnl->expense ?? 0) + ($txPnl->expense ?? 0), 2);
            $farmLoss = round($eventPnl->loss ?? 0, 2);

            $farm = [
                'animals' => $totalAnimals,
                'events_this_month' => $eventsThisMonth,
                'income' => $farmIncome,
                'expense' => $farmExpense,
                'profit' => round($farmIncome - $farmExpense - $farmLoss, 2),
                'investment' => round($eventPnl->investment ?? 0, 2),
            ];
        }

        // ── Shop summary ─────────────────────────────────────────────────────
        $shop = null;
        if ($hasShop) {
            $monthlySales = ShopPosSale::where('account_id', $accountId)
                ->whereDate('sale_datetime', '>=', $monthStart)
                ->whereDate('sale_datetime', '<=', $today);

            $shop = [
                'revenue' => round((clone $monthlySales)->sum('total_amount'), 2),
                'profit' => round((clone $monthlySales)->sum('total_profit'), 2),
                'sales_count' => (clone $monthlySales)->count(),
                'unpaid' => $unpaidSales,
            ];
        }

        // ── Recent activity ──────────────────────────────────────────────────
        $recentActivity = AuditLog::where('account_id', $accountId)
            ->latest()
            ->limit(10)
            ->get(['id', 'action', 'description', 'created_at']);

        return response()->json([
            'has_farm' => $hasFarm,
            'has_shop' => $hasShop,
            'has_devices' => $hasDevices,
            'devices' => $devices,
            'alerts' => $alerts,
            'activity_today' => $activityToday,
            'farm' => $farm,
            'shop' => $shop,
            'recent_activity' => $recentActivity,
        ]);
    }
}
