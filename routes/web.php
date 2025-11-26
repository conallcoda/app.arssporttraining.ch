<?php

use Illuminate\Support\Facades\Route;

use App\Livewire\ItinerarySeasonCreator;
use App\Livewire\SeasonCreator;
use App\Livewire\TrainingPlanner;

Route::get('/', TrainingPlanner::class);
Route::get('/create', SeasonCreator::class);
Route::get('/itinerary', ItinerarySeasonCreator::class);
