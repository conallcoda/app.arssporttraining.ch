<?php

use App\Livewire\BlockCreator;
use App\Livewire\Calculator;
use App\Livewire\Database;
use App\Livewire\Database\AthleteGroupIndex;
use App\Livewire\Database\AthleteIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', Database::class);
Route::get('/athletes', AthleteIndex::class);
Route::get('/athletes/groups', AthleteGroupIndex::class);
Route::get('/schedule', BlockCreator::class);
Route::get('/calculator', Calculator::class);
