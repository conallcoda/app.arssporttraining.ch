<?php

use App\Livewire\BlockCreator;
use App\Livewire\Database\AthleteGroupIndex;
use App\Livewire\Database\AthleteIndex;
use App\Livewire\Database\ExerciseIndex;
use App\Livewire\Training\TrainingPlanIndex;
use App\Livewire\Training\TrainingPlanView;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/training-plans');

Route::get('/athletes', AthleteIndex::class)->name('athlete-index');
Route::get('/athletes/groups', AthleteGroupIndex::class)->name('athlete-group-index');
Route::get('/exercises', ExerciseIndex::class)->name('exercise-index');
Route::get('/training-plans', TrainingPlanIndex::class)->name('training-plan-index');
Route::get('/training-plans/{trainingPlan}', TrainingPlanView::class)->name('training-plan-view');
Route::get('/schedule-test', BlockCreator::class)->name('block-creator');
