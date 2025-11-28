<?php

use App\Livewire\BlockCreator;
use App\Livewire\Documentation;
use App\Livewire\SeasonCreator;
use App\Livewire\TrainingPlanner;
use Illuminate\Support\Facades\Route;

Route::get('/', TrainingPlanner::class);
Route::get('/create', SeasonCreator::class);
Route::get('/block', BlockCreator::class);
Route::get('/documentation', Documentation::class);
