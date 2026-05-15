<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('compares future-open snapshots by default and exits successfully when they match', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Compare Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Compare Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ]);

    $this->artisan('training:snapshot-compare', ['--slot-id' => [$slot->id]])
        ->expectsOutputToContain('Slot '.$slot->id.' matches. [future_open]')
        ->expectsOutputToContain('Compared slots: 1')
        ->expectsOutputToContain('Matching slots: 1')
        ->expectsOutputToContain('Mismatched slots: 0')
        ->assertExitCode(0);
});

it('reports future-open snapshot mismatches and excludes locked slots unless requested', function () {
    Carbon::setTestNow(Carbon::parse('2030-04-21 09:00:00'));

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Compare Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Compare Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $futureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-25 09:00:00'),
    ])->fresh('exercises.sets.values');
    $futureSlot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $lockedSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ])->fresh('exercises.sets.values');
    $lockedSlot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan('training:snapshot-compare')
        ->expectsOutputToContain('Slot '.$futureSlot->id.' mismatches: 1 [future_open]')
        ->expectsOutputToContain('Compared slots: 1')
        ->expectsOutputToContain('Matching slots: 0')
        ->expectsOutputToContain('Mismatched slots: 1')
        ->assertExitCode(1);

    $this->artisan('training:snapshot-compare', ['--include-locked' => true, '--only-mismatches' => true])
        ->expectsOutputToContain('Slot '.$futureSlot->id.' mismatches: 1 [future_open]')
        ->expectsOutputToContain('Slot '.$lockedSlot->id.' mismatches: 1 [locked_past]')
        ->expectsOutputToContain('Compared slots: 2')
        ->expectsOutputToContain('Mismatched slots: 2')
        ->assertExitCode(1);
});
