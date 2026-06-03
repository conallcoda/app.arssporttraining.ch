<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('client-js-log', function ($request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        if ($this->app->environment('local')) {
            Mail::alwaysTo('conall+test@coda.works');
        }
    }
}
