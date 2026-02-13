<?php

use App\Livewire\Database\AthleteGroupIndex;
use App\Livewire\Database\AthleteIndex;
use App\Livewire\Database\CategoryIndex;
use App\Livewire\Database\ExerciseIndex;
use App\Livewire\Training\ProgramCategoryIndex;
use App\Livewire\Training\TrainingPlanIndex;
use App\Livewire\Training\TrainingPlanView;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/training-plans');

Route::get('/athletes', AthleteIndex::class)->name('athlete-index');
Route::get('/athletes/groups', AthleteGroupIndex::class)->name('athlete-group-index');
Route::get('/exercises', ExerciseIndex::class)->name('exercise-index');
Route::get('/exercises/categories', CategoryIndex::class)->name('category-index');
Route::get('/training-plans', TrainingPlanIndex::class)->name('training-plan-index');
Route::get('/training-plans/{trainingPlan}', TrainingPlanView::class)->name('training-plan-view');
Route::get('/training/program-categories', ProgramCategoryIndex::class)->name('program-category-index');

Route::get('/test/exercise-creator', \App\Livewire\Test\ExerciseCreator::class)->name('test.exercise-creator');
