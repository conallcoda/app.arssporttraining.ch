<?php

use Coda\Cms\Registry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/training-plans');

app(Registry::class)->registerRoutes();

Route::get('/test/exercise-creator', \App\Livewire\Test\ExerciseCreator::class)->name('test.exercise-creator');
