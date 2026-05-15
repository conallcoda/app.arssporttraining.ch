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

it('requires explicit slot ids and force for risky snapshot repairs', function () {
    $this->artisan('training:snapshot-repair')
        ->expectsOutputToContain('Provide at least one --slot-id')
        ->assertExitCode(1);
});

it('repairs an explicitly targeted locked-past snapshot when forced', function () {
    Carbon::setTestNow(Carbon::parse('2030-04-21 09:00:00'));

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Repair Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Repair Strength']);
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
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan('training:snapshot-repair', ['--slot-id' => [$slot->id]])
        ->expectsOutputToContain('This command can rewrite locked or ambiguous scheduled snapshots.')
        ->assertExitCode(1);

    $this->artisan('training:snapshot-repair', ['--slot-id' => [$slot->id], '--force' => true])
        ->expectsOutputToContain('Scheduled snapshot repair complete.')
        ->expectsOutputToContain('Audited slots: 1')
        ->expectsOutputToContain('Mismatched slots before repair: 1')
        ->expectsOutputToContain('Repaired slots: 1')
        ->expectsOutputToContain('Still mismatched after repair: 0')
        ->assertExitCode(0);

    $weight = $slot->fresh('exercises.sets.values')->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

    expect((float) $weight->planned_decimal_value)->toBe(82.5);
});
