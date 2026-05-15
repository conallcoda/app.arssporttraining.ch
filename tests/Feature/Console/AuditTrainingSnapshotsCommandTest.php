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

it('reports matching scheduled snapshots and exits successfully', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Audit Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Audit Strength']);
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
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ]);

    $this->artisan('training:snapshot-audit', ['--slot-id' => [$slot->id]])
        ->expectsOutputToContain('Slot '.$slot->id.' matches. [future_open]')
        ->expectsOutputToContain('Audited slots: 1')
        ->expectsOutputToContain('Matching slots: 1')
        ->expectsOutputToContain('Mismatched slots: 0')
        ->expectsOutputToContain('Locked past slots: 0')
        ->expectsOutputToContain('Future open slots: 1')
        ->expectsOutputToContain('Ambiguous boundary slots: 0')
        ->assertExitCode(0);
});

it('reports mismatched scheduled snapshots and exits with failure', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Audit Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Audit Strength']);
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

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan('training:snapshot-audit', ['--slot-id' => [$slot->id]])
        ->expectsOutputToContain('Slot '.$slot->id.' mismatches: 1 [future_open]')
        ->expectsOutputToContain('exercise:'.$exercise->id.'|0||main.set:1.value:weight.planned_decimal_value')
        ->expectsOutputToContain('Audited slots: 1')
        ->expectsOutputToContain('Matching slots: 0')
        ->expectsOutputToContain('Mismatched slots: 1')
        ->expectsOutputToContain('Locked past slots: 0')
        ->expectsOutputToContain('Future open slots: 1')
        ->expectsOutputToContain('Ambiguous boundary slots: 0')
        ->assertExitCode(1);
});
