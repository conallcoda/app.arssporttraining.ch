<?php

use App\Livewire\BlockCreator;
use App\Livewire\Documentation;
use Illuminate\Support\Facades\Route;

Route::get('/', BlockCreator::class);
Route::get('/documentation', Documentation::class);
