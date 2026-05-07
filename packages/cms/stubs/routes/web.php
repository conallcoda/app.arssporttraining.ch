<?php

use Coda\Cms\Registry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::prefix('admin')->middleware('auth')->group(function (): void {
    app(Registry::class)->registerRoutes();
});
