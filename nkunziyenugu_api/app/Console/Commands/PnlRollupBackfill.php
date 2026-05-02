<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\PnlRollupService;
use Illuminate\Console\Command;

class PnlRollupBackfill extends Command
{
    protected $signature = 'farm:pnl:backfill {--account=* : Account IDs to rebuild (default: all)}';
    protected $description = 'Rebuild farm_pnl_monthly rollup from source tables for one or more accounts';

    public function handle(PnlRollupService $svc): int
    {
        $accountIds = $this->option('account');
        $accounts = Account::query()
            ->when($accountIds, fn($q) => $q->whereIn('id', $accountIds))
            ->where('deleted_flag', 0)
            ->pluck('id');

        if ($accounts->isEmpty()) {
            $this->warn('No accounts to process.');
            return self::SUCCESS;
        }

        $this->info("Backfilling rollup for {$accounts->count()} account(s)...");
        $totalCells = 0;
        foreach ($accounts as $accountId) {
            $cells = $svc->refreshAll((int) $accountId);
            $this->line("  account=$accountId cells=$cells");
            $totalCells += $cells;
        }
        $this->info("Done. Refreshed $totalCells cell(s) total.");
        return self::SUCCESS;
    }
}
