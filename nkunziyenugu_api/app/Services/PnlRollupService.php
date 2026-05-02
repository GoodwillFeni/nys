<?php

namespace App\Services;

use App\Models\FarmAnimalEvent;
use App\Models\FarmPnlMonthly;
use App\Models\FarmTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the farm_pnl_monthly rollup table. Recomputes one (account, farm,
 * year, month) cell from the source tables and upserts it. Idempotent.
 *
 * Used in two places:
 *   - PnlMonthlyObserver: recomputes one cell on every event/tx write
 *   - PnlRollupBackfill command: rebuilds everything for an account
 */
class PnlRollupService
{
    /**
     * Recompute and upsert one rollup cell.
     *
     * @param int      $accountId
     * @param int|null $farmId  Null is a separate cell from any specific farm
     * @param int      $year
     * @param int      $month   1..12
     */
    public function refreshCell(int $accountId, ?int $farmId, int $year, int $month): void
    {
        // Animal events bucket
        $eventsQ = FarmAnimalEvent::query()
            ->where('account_id', $accountId)
            ->where('deleted', 0)
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month);
        if ($farmId !== null) $eventsQ->where('farm_id', $farmId);

        $events = $eventsQ->selectRaw("
            COALESCE(SUM(CASE WHEN cost_type = 'income'     THEN cost ELSE 0 END),0) AS events_income,
            COALESCE(SUM(CASE WHEN cost_type = 'expense'    THEN cost ELSE 0 END),0) AS events_expense,
            COALESCE(SUM(CASE WHEN cost_type = 'running'    THEN cost ELSE 0 END),0) AS events_running,
            COALESCE(SUM(CASE WHEN cost_type = 'loss'       THEN cost ELSE 0 END),0) AS events_loss,
            COALESCE(SUM(CASE WHEN cost_type = 'birth'      THEN cost ELSE 0 END),0) AS events_birth,
            COALESCE(SUM(CASE WHEN cost_type = 'investment' THEN cost ELSE 0 END),0) AS events_investment
        ")->first();

        // Transactions bucket
        $txQ = FarmTransaction::query()
            ->where('account_id', $accountId)
            ->where('deleted', 0)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);
        if ($farmId !== null) $txQ->where('farm_id', $farmId);

        $tx = $txQ->selectRaw("
            COALESCE(SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END),0) AS tx_income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END),0) AS tx_expense,
            COALESCE(SUM(CASE WHEN type = 'loss'    THEN amount ELSE 0 END),0) AS tx_loss
        ")->first();

        FarmPnlMonthly::updateOrCreate(
            [
                'account_id' => $accountId,
                'farm_id'    => $farmId,
                'year'       => $year,
                'month'      => $month,
            ],
            [
                'events_income'     => $events->events_income,
                'events_expense'    => $events->events_expense,
                'events_running'    => $events->events_running,
                'events_loss'       => $events->events_loss,
                'events_birth'      => $events->events_birth,
                'events_investment' => $events->events_investment,
                'tx_income'         => $tx->tx_income,
                'tx_expense'        => $tx->tx_expense,
                'tx_loss'           => $tx->tx_loss,
                'refreshed_at'      => now(),
            ]
        );
    }

    /**
     * Refresh ALL cells for an account (and optionally a specific farm).
     * Discovers (year, month, farm_id) tuples from both source tables, then
     * recomputes each one.
     */
    public function refreshAll(int $accountId, ?int $farmId = null): int
    {
        // Find every (year, month, farm) tuple that has any data
        $eventTuples = FarmAnimalEvent::query()
            ->where('account_id', $accountId)
            ->where('deleted', 0)
            ->when($farmId, fn($q) => $q->where('farm_id', $farmId))
            ->selectRaw('YEAR(event_date) AS y, MONTH(event_date) AS m, farm_id')
            ->groupBy('y', 'm', 'farm_id')
            ->get();

        $txTuples = FarmTransaction::query()
            ->where('account_id', $accountId)
            ->where('deleted', 0)
            ->when($farmId, fn($q) => $q->where('farm_id', $farmId))
            ->selectRaw('YEAR(transaction_date) AS y, MONTH(transaction_date) AS m, farm_id')
            ->groupBy('y', 'm', 'farm_id')
            ->get();

        $tuples = $eventTuples->concat($txTuples)
            ->map(fn($r) => "{$r->y}-{$r->m}-" . ($r->farm_id ?? 'null'))
            ->unique()
            ->values();

        $count = 0;
        foreach ($tuples as $key) {
            [$y, $m, $f] = explode('-', $key);
            $this->refreshCell($accountId, $f === 'null' ? null : (int) $f, (int) $y, (int) $m);
            $count++;
        }
        return $count;
    }

    /**
     * Convenience: derive (account, farm, year, month) from a model row and
     * refresh that cell. Used by the observer.
     */
    public function refreshFromEvent(FarmAnimalEvent $event): void
    {
        $d = Carbon::parse($event->event_date);
        $this->refreshCell((int) $event->account_id, $event->farm_id ? (int) $event->farm_id : null, $d->year, $d->month);
    }

    public function refreshFromTransaction(FarmTransaction $tx): void
    {
        $d = Carbon::parse($tx->transaction_date);
        $this->refreshCell((int) $tx->account_id, $tx->farm_id ? (int) $tx->farm_id : null, $d->year, $d->month);
    }
}
