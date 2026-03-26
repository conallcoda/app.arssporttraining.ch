<?php

use App\Providers\AppServiceProvider;
use App\Providers\CmsServiceProvider;
use App\Providers\LivewireProfilerServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    CmsServiceProvider::class,
    VoltServiceProvider::class,
    LivewireProfilerServiceProvider::class,
];
