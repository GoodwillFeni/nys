<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Notifications\ResetPassword;

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
    }
}
