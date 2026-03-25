<?php

use Coda\Cms\Registry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::prefix('admin')->middleware('auth')->group(function (): void {
    app(Registry::class)->registerRoutes();

    Route::get('/api/slot-details', [\App\Http\Controllers\Training\SlotDetailsController::class, '__invoke'])->name('api.slot-details');
    Route::get('/api/slot-week-page', [\App\Http\Controllers\Training\SlotDetailsController::class, 'weekPage'])->name('api.slot-week-page');
    Route::get('/api/slot-member-colors', [\App\Http\Controllers\Training\SlotDetailsController::class, 'memberColors'])->name('api.slot-member-colors');
    Route::get('/api/program-grid-cells', [\App\Http\Controllers\Training\SlotDetailsController::class, 'gridCells'])->name('api.program-grid-cells');
    Route::get('/api/user-day-slots', [\App\Http\Controllers\Training\SlotDetailsController::class, 'userDaySlots'])->name('api.user-day-slots');
});

Route::get('/test/exercise-creator', \App\Livewire\Test\ExerciseCreator::class)->name('test.exercise-creator');
Route::get('/test/portal-demo', \App\Livewire\Test\PortalDemo::class)->name('test.portal-demo');
