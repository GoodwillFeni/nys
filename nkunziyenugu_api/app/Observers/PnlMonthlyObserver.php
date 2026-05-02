<?php

namespace App\Observers;

use App\Models\FarmAnimalEvent;
use App\Models\FarmTransaction;
use App\Services\PnlRollupService;
use Illuminate\Support\Carbon;

/**
 * Keeps farm_pnl_monthly fresh by recomputing the affected cell on every
 * insert/update/delete of FarmAnimalEvent or FarmTransaction.
 *
 * Both create() and update() recompute the row's CURRENT period AND the OLD
 * period (in case event_date or transaction_date changed between months).
 */
class PnlMonthlyObserver
{
    protected PnlRollupService $svc;

    public function __construct(PnlRollupService $svc)
    {
        $this->svc = $svc;
    }

    public function created($model): void   { $this->refresh($model); }
    public function updated($model): void   { $this->refreshOldAndNew($model); }
    public function deleted($model): void   { $this->refresh($model); }
    public function restored($model): void  { $this->refresh($model); }

    protected function refresh($model): void
    {
        if ($model instanceof FarmAnimalEvent) {
            $this->svc->refreshFromEvent($model);
        } elseif ($model instanceof FarmTransaction) {
            $this->svc->refreshFromTransaction($model);
        }
    }

    protected function refreshOldAndNew($model): void
    {
        $this->refresh($model);

        // If date or account/farm moved, also refresh the previous cell
        $dateField = $model instanceof FarmTransaction ? 'transaction_date' : 'event_date';
        $oldDate    = $model->getOriginal($dateField);
        $oldAccount = $model->getOriginal('account_id');
        $oldFarm    = $model->getOriginal('farm_id');

        if (!$oldDate) return;

        $newDate = $model->{$dateField};
        $sameDate    = Carbon::parse($oldDate)->isSameMonth(Carbon::parse($newDate));
        $sameAccount = (int) $oldAccount === (int) $model->account_id;
        $sameFarm    = (int) ($oldFarm ?? 0) === (int) ($model->farm_id ?? 0);

        if ($sameDate && $sameAccount && $sameFarm) return;

        $d = Carbon::parse($oldDate);
        $this->svc->refreshCell(
            (int) $oldAccount,
            $oldFarm ? (int) $oldFarm : null,
            $d->year,
            $d->month
        );
    }
}
