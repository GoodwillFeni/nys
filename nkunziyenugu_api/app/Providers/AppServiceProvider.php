<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Models\FarmAnimalEvent;
use App\Models\FarmTransaction;
use App\Observers\PnlMonthlyObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url')
                . '/ResetPassword'
                . '?token=' . $token
                . '&email=' . urlencode($user->email);
        });

        // Keep farm_pnl_monthly fresh on every event/transaction write.
        FarmAnimalEvent::observe(PnlMonthlyObserver::class);
        FarmTransaction::observe(PnlMonthlyObserver::class);
    }
}
