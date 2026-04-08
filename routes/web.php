<?php

use App\Http\Controllers\Training\SlotDetailsController;
use App\Livewire\Admin\Docs;
use App\Livewire\Athlete\Calendar;
use App\Livewire\Athlete\Record;
use App\Livewire\Test\ExerciseCreator;
use App\Livewire\Test\PortalDemo;
use Coda\Cms\Registry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', Record::class)
        ->name('athlete.dashboard');

    Route::get('/dashboard/calendar', Calendar::class)
        ->name('athlete.dashboard.calendar')
        ->defaults('calendarView', 'day');

    Route::get('/dashboard/calendar/week', Calendar::class)
        ->name('athlete.dashboard.calendar.week')
        ->defaults('calendarView', 'week');
});

Route::prefix('admin')->middleware(['auth', 'cms.admin'])->group(function (): void {
    app(Registry::class)->registerRoutes();

    Route::get('/docs', Docs::class)->name('admin.docs');

    Route::get('/api/slot-details', [SlotDetailsController::class, '__invoke'])->name('api.slot-details');
    Route::get('/api/slot-week-page', [SlotDetailsController::class, 'weekPage'])->name('api.slot-week-page');
    Route::get('/api/slot-member-colors', [SlotDetailsController::class, 'memberColors'])->name('api.slot-member-colors');
    Route::get('/api/program-grid-cells', [SlotDetailsController::class, 'gridCells'])->name('api.program-grid-cells');
    Route::get('/api/user-day-slots', [SlotDetailsController::class, 'userDaySlots'])->name('api.user-day-slots');
});

Route::get('/test/exercise-creator', ExerciseCreator::class)->name('test.exercise-creator');
Route::get('/test/portal-demo', PortalDemo::class)->name('test.portal-demo');
