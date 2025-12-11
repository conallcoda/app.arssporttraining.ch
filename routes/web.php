<?php

use App\Livewire\BlockCreator;
use App\Livewire\Calculator;
use Illuminate\Support\Facades\Route;

// Route::get('/', BlockCreator::class);
Route::get('/calculator', Calculator::class);
