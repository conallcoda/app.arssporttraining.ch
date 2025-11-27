<?php

use Illuminate\Support\Facades\Route;

use App\Livewire\BlockCreator;
use App\Livewire\SeasonCreator;
use App\Livewire\TrainingPlanner;

Route::get('/', TrainingPlanner::class);
Route::get('/create', SeasonCreator::class);
Route::get('/block', BlockCreator::class);
