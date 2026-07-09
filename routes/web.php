<?php

use App\Http\Controllers\AthleteDashboardController;
use App\Http\Controllers\Auth\UserAccountSetupController;
use App\Http\Controllers\ClientJsLogController;
use App\Http\Controllers\Training\ProgramSectionExerciseController;
use App\Http\Controllers\Training\SlotDetailsController;
use App\Livewire\Admin\Docs;
use App\Livewire\Athlete\Calendar;
use App\Livewire\Athlete\ProgramDetails;
use Coda\Cms\Registry;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::post('/client-js-log', ClientJsLogController::class)
    ->middleware('throttle:client-js-log')
    ->name('client-js-log');

Route::middleware('guest')->group(function (): void {
    Route::get('/account-setup/{accountSetupUuid}/{token}', [UserAccountSetupController::class, 'create'])
        ->name('user.account-setup');

    Route::post('/account-setup', [UserAccountSetupController::class, 'store'])
        ->name('user.account-setup.store');
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/dashboard', '/dashboard/train')
        ->name('athlete.dashboard');

    Route::get('/dashboard/calendar', [AthleteDashboardController::class, 'calendarLegacy'])
        ->name('athlete.dashboard.calendar.legacy');

    Route::get('/dashboard/train/{date?}', Calendar::class)
        ->name('athlete.dashboard.train')
        ->defaults('dashboardMode', 'train');

    Route::get('/dashboard/schedule/{date?}', Calendar::class)
        ->name('athlete.dashboard.schedule')
        ->defaults('dashboardMode', 'schedule');

    Route::get('/dashboard/unrecorded', Calendar::class)
        ->name('athlete.dashboard.unrecorded')
        ->defaults('dashboardMode', 'unrecorded');

    Route::get('/dashboard/calendar/day/{date?}', [AthleteDashboardController::class, 'calendarDay'])
        ->name('athlete.dashboard.calendar');

    Route::get('/dashboard/calendar/week/{date?}', [AthleteDashboardController::class, 'calendarWeek'])
        ->name('athlete.dashboard.calendar.week');

    Route::get('/dashboard/calendar/unrecorded', [AthleteDashboardController::class, 'calendarUnrecorded'])
        ->name('athlete.dashboard.calendar.unrecorded');

    Route::get('/programs/{date}/{trainingProgram}', ProgramDetails::class)
        ->name('athlete.programs.show');
});

Route::prefix('admin')->middleware(['auth', 'cms.admin'])->group(function (): void {
    app(Registry::class)->registerRoutes();

    Route::get('/docs', Docs::class)->name('admin.docs');

    Route::get('/api/slot-details', [SlotDetailsController::class, '__invoke'])->name('api.slot-details');
    Route::get('/api/slot-week-page', [SlotDetailsController::class, 'weekPage'])->name('api.slot-week-page');
    Route::get('/api/slot-member-colors', [SlotDetailsController::class, 'memberColors'])->name('api.slot-member-colors');
    Route::get('/api/program-grid-cells', [SlotDetailsController::class, 'gridCells'])->name('api.program-grid-cells');
    Route::get('/api/user-day-slots', [SlotDetailsController::class, 'userDaySlots'])->name('api.user-day-slots');

    Route::delete('/programs/{exerciseProgram}/sections/{section}/exercises/{programExercise}', [ProgramSectionExerciseController::class, 'destroy'])
        ->name('training.programs.sections.exercises.destroy');
});
