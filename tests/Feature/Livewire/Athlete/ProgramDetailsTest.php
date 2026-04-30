<?php

use App\Livewire\Athlete\ProgramDetails;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('shows scheduled programs on the athlete day calendar page', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');
    config()->set('athlete.require_readiness_for_training_visibility', false);

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Front Squat'])->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/dashboard/calendar/day/2026-04-03')
        ->assertOk()
        ->assertSee('09:00');
});

it('shows all exercises in the selected program', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseOne = Exercise::factory()->create([
        'name' => 'Front Squat',
        'instructions' => 'Stay tall through the lift.',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => [
                'default' => 4,
                'label' => 'Set',
                'deload' => 'none',
            ],
            'reps' => [
                'mode' => 'manual',
                'default' => 8,
                'applyPer' => 'session',
            ],
            'weight' => [
                'mode' => 'manual',
                'default' => 5,
                'applyPer' => 'session',
            ],
            'tempo' => [
                'default' => '2020',
                'applyPer' => 'week',
            ],
            'rest' => [
                'default' => 45,
                'applyPer' => 'week',
            ],
            'preview' => [
                'weeks' => 1,
                'sessionsPerWeek' => 1,
            ],
        ],
    ]);
    $exerciseTwo = Exercise::factory()->create([
        'name' => 'Romanian Deadlift',
        'instructions' => 'Keep tension in the hamstrings.',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseOne->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseTwo->id,
        'sort' => 1,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/programs/2026-04-03/'.$trainingProgram->id.'?from=%2Fdashboard%2Fcalendar%2Fweek%2F2026-03-30')
        ->assertOk()
        ->assertSeeLivewire(ProgramDetails::class)
        ->assertSee('Friday Strength')
        ->assertSee('Front Squat')
        ->assertSee('Romanian Deadlift')
        ->assertSee('Reps')
        ->assertSee('Weight (kg)')
        ->assertSee('Tempo')
        ->assertSee('2020')
        ->assertSee('Rest')
        ->assertSee('45')
        ->assertSee('Stay tall through the lift.')
        ->assertSee('/dashboard/calendar/week/2026-03-30', false);
});

it('returns 404 when the selected program is not scheduled for the athlete on that date', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/programs/2026-04-03/999999')
        ->assertNotFound();
});

it('lets athletes mark an exercise done or skipped from the materialized session', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseOne = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);
    $exerciseTwo = Exercise::factory()->create([
        'name' => 'Split Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseOne->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseTwo->id,
        'sort' => 1,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises');

    $exerciseOneSlot = $slot->exercises->sortBy('sort')->values()->get(0);
    $exerciseTwoSlot = $slot->exercises->sortBy('sort')->values()->get(1);

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markExerciseCompleted', $exerciseOneSlot->id)
        ->call('markExerciseSkipped', $exerciseTwoSlot->id);

    $slot = $slot->fresh('exercises.sets');
    $exerciseOneSlot = $slot->exercises->firstWhere('id', $exerciseOneSlot->id);
    $exerciseTwoSlot = $slot->exercises->firstWhere('id', $exerciseTwoSlot->id);

    expect($exerciseOneSlot->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($exerciseTwoSlot->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Skipped)
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::Completed)
        ->and($slot->completed_exercise_count)->toBe(1)
        ->and($slot->skipped_exercise_count)->toBe(1)
        ->and($slot->pending_exercise_count)->toBe(0);

    CarbonImmutable::setTestNow();
});

it('shows the athlete edit pencil when exercise editing is enabled', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.allow_athlete_edits', true);

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create([
            'name' => 'Front Squat',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            ],
        ])->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/programs/2030-04-03/'.$trainingProgram->id)
        ->assertOk()
        ->assertSee('Edit exercise values');
});

it('saves athlete-edited values, keeps modification flags on completion, and allows later explicit resaving', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.allow_athlete_edits', true);

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'duration'],
            'sets' => ['default' => 1, 'label' => 'Interval', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'duration' => ['unit' => 'mm:ss', 'default' => '1:00', 'applyPer' => 'session'],
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

    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->assertSet("editValues.{$slotSet->id}.reps", 6)
        ->assertSet("editValues.{$slotSet->id}.duration", '1:00')
        ->set("editValues.{$slotSet->id}.reps", '8')
        ->set("editValues.{$slotSet->id}.duration", '1:15')
        ->call('saveExerciseEdits')
        ->call('markExerciseCompleted', $slotExercise->id)
        ->call('openExerciseEditor', $slotExercise->id)
        ->set("editValues.{$slotSet->id}.duration", '1:20')
        ->call('saveExerciseEdits');

    $slot = $slot->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $repsValue = $slotSet->values->firstWhere('setting_key', 'reps');
    $durationValue = $slotSet->values->firstWhere('setting_key', 'duration');

    expect($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::CompletedWithModification)
        ->and($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($slotExercise->modified_set_count)->toBe(1)
        ->and($slotExercise->has_any_modification)->toBeTrue()
        ->and($slot->has_any_modification)->toBeTrue()
        ->and($repsValue->actual_value_type)->toBe('string')
        ->and($repsValue->actual_string_value)->toBe('8')
        ->and($repsValue->is_modified)->toBeTrue()
        ->and($durationValue->actual_value_type)->toBe('int')
        ->and($durationValue->actual_int_value)->toBe(80)
        ->and($durationValue->is_modified)->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('does not mark values modified when an athlete explicitly saves the planned values', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.allow_athlete_edits', true);

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
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
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->set("editValues.{$slotSet->id}.reps", '6')
        ->call('saveExerciseEdits');

    $value = $slotSet->fresh('values')->values->firstWhere('setting_key', 'reps');

    expect($value->actual_value_type)->toBeNull()
        ->and($value->is_modified)->toBeFalse();

    CarbonImmutable::setTestNow();
});
