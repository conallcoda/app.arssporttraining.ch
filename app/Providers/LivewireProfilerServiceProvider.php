<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireProfilerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('app.debug')) {
            return;
        }

        Livewire::setUpdateRoute(function ($handle) {
            return \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                ->middleware(['web', \App\Http\LivewireProfilerMiddleware::class]);
        });
    }
}
