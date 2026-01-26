<?php

use App\Livewire\BlockCreator;
use App\Livewire\Calculator;
use App\Livewire\Database;
use App\Livewire\Database\AthleteGroupIndex;
use App\Livewire\Database\AthleteIndex;
use App\Livewire\Database\ExerciseIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', Database::class);
Route::get('/athletes', AthleteIndex::class);
Route::get('/athletes/groups', AthleteGroupIndex::class);
Route::get('/exercises', ExerciseIndex::class);
Route::get('/schedule', BlockCreator::class);
Route::get('/calculator', Calculator::class);
