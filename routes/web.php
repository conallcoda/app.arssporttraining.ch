<?php

use App\Http\Controllers\Auth\UserAccountSetupController;
use App\Http\Controllers\Training\SlotDetailsController;
use App\Livewire\Admin\Docs;
use App\Livewire\Athlete\Calendar;
use App\Livewire\Athlete\ProgramDetails;
use App\Livewire\Test\ExerciseCreator;
use App\Livewire\Test\PortalDemo;
use App\Livewire\Test\ReadinessForm;
use Coda\Cms\Registry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/ping-test', fn () => response('ok', 200))
    ->name('ping-test');

Route::middleware('guest')->group(function (): void {
    Route::get('/account-setup/{accountSetupUuid}/{token}', [UserAccountSetupController::class, 'create'])
        ->name('user.account-setup');

    Route::post('/account-setup', [UserAccountSetupController::class, 'store'])
        ->name('user.account-setup.store');
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/dashboard', '/dashboard/train')
        ->name('athlete.dashboard');

    Route::get('/dashboard/calendar', function (Request $request) {
        return redirect()->route('athlete.dashboard.train', [
            'date' => $request->query('date'),
        ]);
    })->name('athlete.dashboard.calendar.legacy');

    Route::get('/dashboard/train/{date?}', Calendar::class)
        ->name('athlete.dashboard.train')
        ->defaults('dashboardMode', 'train');

    Route::get('/dashboard/schedule/{date?}', Calendar::class)
        ->name('athlete.dashboard.schedule')
        ->defaults('dashboardMode', 'schedule');

    Route::get('/dashboard/unrecorded', Calendar::class)
        ->name('athlete.dashboard.unrecorded')
        ->defaults('dashboardMode', 'unrecorded');

    Route::get('/dashboard/calendar/day/{date?}', function (?string $date = null) {
        return redirect()->route('athlete.dashboard.train', array_filter([
            'date' => $date,
        ]));
    })->name('athlete.dashboard.calendar');

    Route::get('/dashboard/calendar/week/{date?}', function (?string $date = null) {
        return redirect()->route('athlete.dashboard.schedule', array_filter([
            'date' => $date !== null
                ? CarbonImmutable::parse($date)->startOfWeek()->format('Y-m-d')
                : null,
        ]));
    })->name('athlete.dashboard.calendar.week');

    Route::get('/dashboard/calendar/unrecorded', function () {
        return redirect()->route('athlete.dashboard.unrecorded');
    })->name('athlete.dashboard.calendar.unrecorded');

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
});

Route::middleware('auth')->group(function (): void {
    Route::get('/test/exercise-creator', ExerciseCreator::class)->name('test.exercise-creator');
    Route::get('/test/portal-demo', PortalDemo::class)->name('test.portal-demo');
    Route::get('/test/readiness', ReadinessForm::class)->name('test.readiness');
});
