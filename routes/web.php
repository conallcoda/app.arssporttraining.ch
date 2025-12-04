<?php

use App\Livewire\BlockCreator;
use App\Livewire\ProgressionExample;
use Illuminate\Support\Facades\Route;

Route::get('/', BlockCreator::class);
Route::get('/example', ProgressionExample::class);
