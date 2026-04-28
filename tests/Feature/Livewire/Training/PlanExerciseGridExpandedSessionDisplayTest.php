<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows session-specific values for expanded mixed past and future weeks', function () {
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

    $component = Livewire::test(PlanExerciseGrid::class, [
        'exercisePlanId' => $program->id,
        'planType' => ExerciseProgram::class,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [true, false],
        ],
    ]);

    $component->call('updateCellOverride', 0, 0, 'reps', 14, 1, false);

    $grid = $component->instance()->previewGrid;
    $row = collect($grid->rows)->firstWhere('field', 'reps');

    expect($row)->not->toBeNull()
        ->and($row->getCellValue(0, 0, 0))->toBe(12)
        ->and($row->getCellValue(0, 0, 1))->toBe(14)
        ->and($row->isCellOverriddenAt(0, 0, 0))->toBeFalse()
        ->and($row->isCellOverriddenAt(0, 0, 1))->toBeTrue();
});
