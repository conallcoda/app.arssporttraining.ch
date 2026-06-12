<?php

namespace Coda\FormKit;

use Illuminate\Support\ServiceProvider;

class FormKitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'form-kit');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/form-kit'),
        ], 'form-kit-views');
    }
}
