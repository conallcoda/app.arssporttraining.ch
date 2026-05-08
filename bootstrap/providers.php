<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\CmsServiceProvider::class,
    App\Providers\LivewireProfilerServiceProvider::class,
    App\Providers\VoltServiceProvider::class,
];

if (env('TELESCOPE_ENABLED', false)) {
    $providers[] = App\Providers\TelescopeServiceProvider::class;
}

return $providers;
