<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarProgramsView;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingPlanValueRevision;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionRebuildDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('rebuilds only the edited athlete for athlete-specific exercise disable changes', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();
    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id, $user->id);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)->test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(
            start: '2026-03-02',
            end: '2026-03-08',
            preset: 'week',
        ),
        'weekStartsOn' => $weekStartsOn,
        'weekEndsOn' => ($weekStartsOn + 6) % 7,
    ])->call('toggleExerciseDisabled', $pivot->id, $program->id);

    expect(TrainingRevisionBatch::query()
        ->where('domain', 'plan')
        ->where('changed_by', $coach->id)
        ->exists())->toBeTrue()
        ->and(TrainingPlanValueRevision::query()
            ->where('program_exercise_id', $pivot->id)
            ->where('setting_key', 'disabled')
            ->where('after_int_value', 1)
            ->exists())->toBeTrue();
});
