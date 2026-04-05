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
            SUM(CASE WHEN cost_type = 'birth' THEN cost ELSE 0 END) as birth,
            SUM(CASE WHEN cost_type = 'investment' THEN cost ELSE 0 END) as investment
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

        // ── Operating totals (excludes investment) ───────────────────────────
        $opIncome = ($eventSummary->income ?? 0) + ($eventSummary->birth ?? 0) + ($txSummary->income ?? 0);
        $opExpense = ($eventSummary->expense ?? 0) + ($eventSummary->running ?? 0) + ($txSummary->expense ?? 0);
        $opLoss = ($eventSummary->loss ?? 0) + ($txSummary->loss ?? 0);
        $opProfit = $opIncome - $opExpense - $opLoss;

        // ── Capital (investment) ─────────────────────────────────────────────
        $totalInvestment = $eventSummary->investment ?? 0;
        $totalBirth = $eventSummary->birth ?? 0;

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'operating' => [
                'income' => round($opIncome, 2),
                'expense' => round($opExpense, 2),
                'loss' => round($opLoss, 2),
                'profit' => round($opProfit, 2),
            ],
            'capital' => [
                'investment' => round($totalInvestment, 2),
                'birth_value' => round($totalBirth, 2),
            ],
            'animal_events' => [
                'income' => round($eventSummary->income ?? 0, 2),
                'expense' => round($eventSummary->expense ?? 0, 2),
                'running' => round($eventSummary->running ?? 0, 2),
                'loss' => round($eventSummary->loss ?? 0, 2),
                'birth' => round($eventSummary->birth ?? 0, 2),
                'investment' => round($eventSummary->investment ?? 0, 2),
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
