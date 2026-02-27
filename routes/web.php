<?php

use Coda\Cms\Registry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/programs');

app(Registry::class)->registerRoutes();

Route::get('/test/exercise-creator', \App\Livewire\Test\ExerciseCreator::class)->name('test.exercise-creator');
Route::get('/test/portal-demo', \App\Livewire\Test\PortalDemo::class)->name('test.portal-demo');
