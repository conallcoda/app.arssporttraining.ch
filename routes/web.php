<?php

use App\Livewire\BlockCreator;
use App\Livewire\Documentation;
use App\Livewire\ProgressionExample;
use Illuminate\Support\Facades\Route;

Route::get('/', BlockCreator::class);
Route::get('/documentation', Documentation::class);
Route::get('/example', ProgressionExample::class);
