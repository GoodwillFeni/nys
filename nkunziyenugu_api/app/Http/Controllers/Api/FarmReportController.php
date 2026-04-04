<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmTransaction;
use App\Models\FarmAnimalEvent;
use Illuminate\Support\Facades\DB;

class FarmReportController extends Controller
{
    public function pnl(Request $request)
    {
        $accountId = $request->account_id
            ?? $request->header('X-Account-ID');

        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to = $request->to ?? now()->toDateString();

        // ── Animal Events P&L ────────────────────────────────────────────────
        $eventQuery = FarmAnimalEvent::where('deleted', 0)
            ->whereBetween('event_date', [$from, $to]);

        if ($accountId) $eventQuery->where('account_id', $accountId);
        if ($request->farm_id) $eventQuery->where('farm_id', $request->farm_id);

        $eventSummary = (clone $eventQuery)->selectRaw("
            SUM(CASE WHEN cost_type = 'income' THEN cost ELSE 0 END) as income,
            SUM(CASE WHEN cost_type = 'expense' THEN cost ELSE 0 END) as expense,
            SUM(CASE WHEN cost_type = 'running' THEN cost ELSE 0 END) as running,
            SUM(CASE WHEN cost_type = 'loss' THEN cost ELSE 0 END) as loss,
            SUM(CASE WHEN cost_type = 'birth' THEN cost ELSE 0 END) as birth
        ")->first();

        // Breakdown by event type
        $eventBreakdown = (clone $eventQuery)
            ->selectRaw('event_type, cost_type, SUM(cost) as total, COUNT(*) as count')
            ->groupBy('event_type', 'cost_type')
            ->get();

        // ── Inventory Transactions P&L ───────────────────────────────────────
        $txQuery = FarmTransaction::where('deleted', 0)
            ->whereBetween('transaction_date', [$from, $to]);

        if ($accountId) $txQuery->where('account_id', $accountId);
        if ($request->farm_id) $txQuery->where('farm_id', $request->farm_id);

        $txSummary = (clone $txQuery)->selectRaw("
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
            SUM(CASE WHEN type = 'loss' THEN amount ELSE 0 END) as loss
        ")->first();

        // Breakdown by category
        $txBreakdown = (clone $txQuery)
            ->selectRaw('category, type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category', 'type')
            ->get();

        // ── Combined totals ──────────────────────────────────────────────────
        $totalIncome = ($eventSummary->income ?? 0) + ($eventSummary->birth ?? 0) + ($txSummary->income ?? 0);
        $totalExpense = ($eventSummary->expense ?? 0) + ($eventSummary->running ?? 0) + ($txSummary->expense ?? 0);
        $totalLoss = ($eventSummary->loss ?? 0) + ($txSummary->loss ?? 0);
        $profit = $totalIncome - $totalExpense - $totalLoss;

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'income' => round($totalIncome, 2),
                'expense' => round($totalExpense, 2),
                'loss' => round($totalLoss, 2),
                'profit' => round($profit, 2),
            ],
            'animal_events' => [
                'income' => round($eventSummary->income ?? 0, 2),
                'expense' => round($eventSummary->expense ?? 0, 2),
                'running' => round($eventSummary->running ?? 0, 2),
                'loss' => round($eventSummary->loss ?? 0, 2),
                'birth' => round($eventSummary->birth ?? 0, 2),
                'breakdown' => $eventBreakdown,
            ],
            'inventory' => [
                'income' => round($txSummary->income ?? 0, 2),
                'expense' => round($txSummary->expense ?? 0, 2),
                'loss' => round($txSummary->loss ?? 0, 2),
                'breakdown' => $txBreakdown,
            ],
        ]);
    }
}
