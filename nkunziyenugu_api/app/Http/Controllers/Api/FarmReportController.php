<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FarmAnimalEvent;
use App\Models\FarmPnlMonthly;
use App\Models\FarmTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * P&L report — backed by farm_pnl_monthly rollup table for fast lifetime totals.
 *
 * - Default request (no from/to): returns LIFETIME totals (one SUM over rollup).
 * - With from/to: queries the rollup filtered by (year, month) — still few rows.
 * - With ?detail=1: ALSO computes event_type/category breakdowns from source
 *   tables (cached 5 min) for drill-down.
 */
class FarmReportController extends Controller
{
    public function pnl(Request $request)
    {
        $accountId = (int) ($request->account_id ?? $request->header('X-Account-ID'));
        $farmId    = $request->farm_id ? (int) $request->farm_id : null;
        $from      = $request->input('from');
        $to        = $request->input('to');
        $wantDetail = (bool) $request->boolean('detail');

        if (!$accountId) {
            return response()->json(['status' => 'error', 'message' => 'Active account not selected'], 400);
        }

        // ── Aggregate the rollup ─────────────────────────────────────────────
        $rollup = FarmPnlMonthly::query()->where('account_id', $accountId);
        if ($farmId !== null) $rollup->where('farm_id', $farmId);

        if ($from || $to) {
            // (year*100+month) sortable key — filter without month-by-month gymnastics
            $fromKey = $from ? $this->dateToYm($from) : 0;
            $toKey   = $to   ? $this->dateToYm($to)   : 999912;
            $rollup->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [$fromKey, $toKey]);
        }

        $totals = $rollup->selectRaw("
            COALESCE(SUM(events_income),     0) AS events_income,
            COALESCE(SUM(events_expense),    0) AS events_expense,
            COALESCE(SUM(events_running),    0) AS events_running,
            COALESCE(SUM(events_loss),       0) AS events_loss,
            COALESCE(SUM(events_birth),      0) AS events_birth,
            COALESCE(SUM(events_investment), 0) AS events_investment,
            COALESCE(SUM(tx_income),         0) AS tx_income,
            COALESCE(SUM(tx_expense),        0) AS tx_expense,
            COALESCE(SUM(tx_loss),           0) AS tx_loss
        ")->first();

        // ── Operating (cash) — excludes birth (natural increase) and investment ──
        $opIncome  = (float) $totals->events_income + (float) $totals->tx_income;
        $opExpense = (float) $totals->events_expense + (float) $totals->events_running + (float) $totals->tx_expense;
        $opLoss    = (float) $totals->events_loss + (float) $totals->tx_loss;
        $opProfit  = $opIncome - $opExpense - $opLoss;

        // ── Natural increase (births grow herd value, no cash) ───────────────
        $natural = (float) $totals->events_birth;

        // ── Capital (owner injection) ────────────────────────────────────────
        $investment = (float) $totals->events_investment;

        $equityChange = $opProfit + $natural + $investment;

        $response = [
            'period' => [
                'from'     => $from,
                'to'       => $to,
                'lifetime' => !$from && !$to,
            ],
            'operating' => [
                'income'  => round($opIncome, 2),
                'expense' => round($opExpense, 2),
                'loss'    => round($opLoss, 2),
                'profit'  => round($opProfit, 2),
            ],
            'natural_increase' => [
                'birth_value' => round($natural, 2),
            ],
            'capital' => [
                'investment'  => round($investment, 2),
                'birth_value' => round($natural, 2), // backward compat
            ],
            'total_equity_change' => round($equityChange, 2),
            'animal_events' => [
                'income'     => round((float) $totals->events_income, 2),
                'expense'    => round((float) $totals->events_expense, 2),
                'running'    => round((float) $totals->events_running, 2),
                'loss'       => round((float) $totals->events_loss, 2),
                'birth'      => round((float) $totals->events_birth, 2),
                'investment' => round((float) $totals->events_investment, 2),
            ],
            'inventory' => [
                'income'  => round((float) $totals->tx_income, 2),
                'expense' => round((float) $totals->tx_expense, 2),
                'loss'    => round((float) $totals->tx_loss, 2),
            ],
        ];

        // Optional drill-down breakdowns from source tables (cached 5 min)
        if ($wantDetail) {
            $cacheKey = sprintf(
                'pnl:detail:%d:%s:%s:%s',
                $accountId,
                $farmId ?? 'all',
                $from ?? 'lifetime',
                $to ?? 'now'
            );
            $detail = Cache::remember($cacheKey, 300, function () use ($accountId, $farmId, $from, $to) {
                $eventQ = FarmAnimalEvent::where('deleted', 0)->where('account_id', $accountId);
                $txQ    = FarmTransaction::where('deleted', 0)->where('account_id', $accountId);
                if ($farmId !== null) {
                    $eventQ->where('farm_id', $farmId);
                    $txQ->where('farm_id', $farmId);
                }
                if ($from) {
                    $eventQ->where('event_date', '>=', $from);
                    $txQ->where('transaction_date', '>=', $from);
                }
                if ($to) {
                    $eventQ->where('event_date', '<=', $to);
                    $txQ->where('transaction_date', '<=', $to);
                }
                return [
                    'event_breakdown' => $eventQ
                        ->selectRaw('event_type, cost_type, SUM(cost) as total, COUNT(*) as count')
                        ->groupBy('event_type', 'cost_type')->get(),
                    'tx_breakdown' => $txQ
                        ->selectRaw('category, type, SUM(amount) as total, COUNT(*) as count')
                        ->groupBy('category', 'type')->get(),
                ];
            });
            $response['animal_events']['breakdown'] = $detail['event_breakdown'];
            $response['inventory']['breakdown']     = $detail['tx_breakdown'];
        }

        return response()->json($response);
    }

    /** "2026-04-15" → 202604 (sortable year-month key). */
    private function dateToYm(string $date): int
    {
        $d = Carbon::parse($date);
        return $d->year * 100 + $d->month;
    }
}
