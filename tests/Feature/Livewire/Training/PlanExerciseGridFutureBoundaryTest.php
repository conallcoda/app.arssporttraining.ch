<?php

use App\Livewire\Training\View\PlanExerciseGrid;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Data\Exercise\Preview\PreviewGrid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('applies settings changes without creating historical overrides during settings save', function () {
    Carbon::setTestNow('2026-04-17 12:00:00');

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $updatedConfig = $exercise->config->toArray();
    $updatedConfig['reps']['default'] = 8;

    $component = Livewire::test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 2,
        'sessionsPerWeek' => 1,
        'weekSessionDates' => [
            ['2026-04-10'],
            ['2026-04-24'],
        ],
        'lockedSessionsByWeek' => [
            [true],
            [false],
        ],
    ]);

    /** @var PreviewGrid $before */
    $before = $component->instance()->previewGrid;

    $component->call('onSettingsSaved', [
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'config' => $updatedConfig,
    ]);

    /** @var PreviewGrid $after */
    $after = $component->instance()->previewGrid;

    $overrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);

    expect($overrides->startsAtDate)->toBeNull()
        ->and($overrides->reps?->default)->toBe(8)
        ->and($overrides->historicalGridOverrides['cells'] ?? [])->toBe([])
        ->and($overrides->historicalGridOverrides['sessions'] ?? [])->toBe([])
        ->and($before->rows[0]->cells[0][0] ?? null)->toBe(6)
        ->and($after->rows[0]->cells[0][0] ?? null)->toBe(8)
        ->and($after->rows[0]->cells[1][0] ?? null)->toBe(8);
});

it('persists apply-per session changes from the settings modal and rebuilds the grid shape', function () {
    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'rest' => ['default' => 60, 'applyPer' => 'session'],
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
        'planId' => $program->id,
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'weeks' => 1,
        'sessionsPerWeek' => 2,
        'weekSessionDates' => [
            ['2026-04-27', '2026-04-30'],
        ],
        'lockedSessionsByWeek' => [
            [false, false],
        ],
    ]);

    expect(collect($component->instance()->previewGrid->rows)->pluck('field')->all())
        ->toContain('rest')
        ->and(collect($component->instance()->previewGrid->weekColumns)->pluck('field')->all())
        ->not->toContain('rest');

    $updatedConfig = $exercise->config->toArray();
    $updatedConfig['rest']['applyPer'] = 'per_session';

    $component->call('onSettingsSaved', [
        'programExerciseId' => $pivot->id,
        'exerciseId' => $exercise->id,
        'userId' => null,
        'config' => $updatedConfig,
    ]);

    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);

    expect(\App\Support\Training\ApplyPerScope::normalize($savedOverrides->rest?->applyPer))->toBe('session')
        ->and(collect($component->instance()->previewGrid->rows)->pluck('field')->all())
            ->not->toContain('rest')
        ->and(collect($component->instance()->previewGrid->weekColumns)->pluck('field')->all())
            ->toContain('rest');
});
