<?php

use App\Livewire\Athlete\ProgramDetails;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingActualValueRevision;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\Athlete\ProgramDetailsExerciseViewBuilder;
use App\Support\Training\ScheduledSessionSnapshotBuilder;
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
        ->get('/dashboard/train/2026-04-03')
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
        ->get('/programs/2026-04-03/'.$trainingProgram->id.'?from=%2Fdashboard%2Fschedule%2F2026-03-30')
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
        ->assertSee('/dashboard/schedule/2026-03-30', false);
});

it('hides exercises that depend on missing automatic metrics on the athlete dashboard', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $autoWeightExercise = Exercise::factory()->create([
        'name' => 'Auto Front Squat',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
        ],
    ]);

    $autoHeartRateExercise = Exercise::factory()->create([
        'name' => 'Auto Jogging',
        'config' => [
            'settings' => ['duration', 'heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'duration' => ['unit' => 'seconds', 'default' => 60, 'applyPer' => 'session'],
            'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '2', 'applyPer' => 'session'],
        ],
    ]);

    $manualExercise = Exercise::factory()->create([
        'name' => 'Manual Split Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $autoWeightExercise->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $autoHeartRateExercise->id,
        'sort' => 1,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $manualExercise->id,
        'sort' => 2,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/programs/2026-04-03/'.$trainingProgram->id)
        ->assertOk()
        ->assertSee('Manual Split Squat')
        ->assertDontSee('Auto Front Squat')
        ->assertDontSee('Auto Jogging');
});

it('defaults to the warm up tab when the session includes warm up exercises', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $warmUpExercise = Exercise::factory()->create(['name' => 'Jog Prep']);
    $mainExercise = Exercise::factory()->create(['name' => 'Front Squat']);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $warmUpExercise->id,
        'sort' => 0,
        'type' => 'warm_up',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $mainExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2026-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->assertSet('activeSection', 'warm_up')
        ->assertSee('Jog Prep')
        ->assertDontSee('Front Squat');
});

it('sorts athlete program exercises by group before sort order within a section', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseA2 = Exercise::factory()->create(['name' => 'Split Squat']);
    $exerciseB1 = Exercise::factory()->create(['name' => 'Push Press']);
    $exerciseA1 = Exercise::factory()->create(['name' => 'Front Squat']);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseA2->id,
        'sort' => 1,
        'group' => 'A',
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseB1->id,
        'sort' => 0,
        'group' => 'B',
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseA1->id,
        'sort' => 0,
        'group' => 'A',
        'type' => 'main',
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2026-04-03',
            'trainingProgram' => $trainingProgram,
        ]);

    expect(collect($component->instance()->programExercises)->pluck('name')->all())->toBe([
        'Front Squat',
        'Split Squat',
        'Push Press',
    ]);
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

it('requires athlete-entered values before marking an exercise done', function () {
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

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create([
            'name' => 'Athlete Enters Weight',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => null, 'applyPer' => 'session'],
            ],
        ])->id,
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
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertSet('editingExerciseId', $slotExercise->id)
        ->assertSet('activeEditSet', 'set-'.$slotSet->id)
        ->assertNotDispatched('athlete-exercise-action-succeeded')
        ->assertSee('This field is required.');

    $slotExercise = $slotExercise->fresh('sets.values');

    expect($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending)
        ->and($slotExercise->sets->first()?->status)->toBe(TrainingProgramSlotSetStatusEnum::Pending)
        ->and($slotExercise->sets->first()?->values->firstWhere('setting_key', 'weight')?->actual_value_type)->toBeNull();

    CarbonImmutable::setTestNow();
});

it('shows blank athlete-entered weight rows in the athlete dashboard grid', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2030');

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
            'name' => 'Athlete Enters Weight',
            'instructions' => 'Athlete should enter the load used for the set when needed.',
            'config' => [
                'settings' => ['reps', 'weight', 'tempo', 'rest'],
                'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => null, 'applyPer' => 'session'],
                'tempo' => ['default' => '3010', 'applyPer' => 'week'],
                'rest' => ['default' => 30, 'applyPer' => 'week'],
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
        ->assertSee('Weight (kg)');
});

it('dispatches a success event only after an exercise is marked done successfully', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

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
            'name' => 'Athlete Enters Weight',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => 12.5, 'applyPer' => 'session'],
            ],
        ])->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises');

    $slotExercise = $slot->exercises->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertDispatched('athlete-exercise-action-succeeded', exerciseId: $slotExercise->id);

    CarbonImmutable::setTestNow();
});

it('treats zero as a valid athlete-entered value when marking an exercise done', function () {
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

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create([
            'name' => 'Athlete Enters Weight',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => null, 'applyPer' => 'session'],
            ],
        ])->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();

    $slotSet->values->firstWhere('setting_key', 'weight')?->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 0,
        'actual_is_explicit' => true,
        'actual_recorded_by' => $athlete->id,
        'actual_recorded_at' => now(),
        'actual_source' => 'athlete',
    ]);

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertDontSee('This field is required.');

    $slot = $slot->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();
    $weightValue = $slotExercise->sets->first()?->values->firstWhere('setting_key', 'weight');

    expect($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($slotExercise->sets->first()?->status)->toBe(TrainingProgramSlotSetStatusEnum::CompletedWithModification)
        ->and($weightValue?->actual_value_type)->toBe('int')
        ->and($weightValue?->actual_int_value)->toBe(0)
        ->and($weightValue?->actual_is_explicit)->toBeTrue()
        ->and($weightValue?->is_modified)->toBeTrue();

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

it('records state revision batches when an athlete completes and skips exercises', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

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
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create([
            'name' => 'Split Squat',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            ],
        ])->id,
        'sort' => 1,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets');

    $completedExercise = $slot->exercises->sortBy('sort')->values()->get(0);
    $skippedExercise = $slot->exercises->sortBy('sort')->values()->get(1);
    $completedSet = $completedExercise->sets->first();
    $skippedSet = $skippedExercise->sets->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markExerciseCompleted', $completedExercise->id)
        ->call('markExerciseSkipped', $skippedExercise->id);

    expect(TrainingRevisionBatch::query()
        ->where('domain', 'state')
        ->where('action', 'mark_exercise_completed')
        ->exists())->toBeTrue()
        ->and(TrainingRevisionBatch::query()
            ->where('domain', 'state')
            ->where('action', 'mark_exercise_skipped')
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', \App\Models\Training\TrainingProgramSlotExercise::class)
            ->where('subject_id', $completedExercise->id)
            ->where('after_value', TrainingProgramSlotExerciseStatusEnum::Completed->value)
            ->where('changed_by', $athlete->id)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', \App\Models\Training\TrainingProgramSlotExercise::class)
            ->where('subject_id', $skippedExercise->id)
            ->where('after_value', TrainingProgramSlotExerciseStatusEnum::Skipped->value)
            ->where('changed_by', $athlete->id)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', \App\Models\Training\TrainingProgramSlotSet::class)
            ->where('subject_id', $completedSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Completed->value)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', \App\Models\Training\TrainingProgramSlotSet::class)
            ->where('subject_id', $skippedSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Skipped->value)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramSlot::class)
            ->where('subject_id', $slot->id)
            ->where('after_value', TrainingProgramSlotStatusEnum::Completed->value)
            ->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
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

it('lets athletes skip and unskip an individual set from the editor', function () {
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
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
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
    $slotSet = $slotExercise->sets->sortBy('set_number')->values()->get(0);

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->call('markEditSetSkipped', $slotSet->id)
        ->assertSee('This set was skipped.');

    $slotSet = $slotSet->fresh('values');

    expect($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Pending)
        ->and($slotSet->skipped_at)->toBeNull();

    $component->call('saveExerciseEdits');

    $slotSet = $slotSet->fresh('values');

    expect($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Pending)
        ->and($slotSet->skipped_at)->toBeNull();

    $component->call('markExerciseCompleted', $slotExercise->id);

    $slotSet = $slotSet->fresh('values');

    expect($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Skipped)
        ->and($slotSet->skipped_at)->not->toBeNull()
        ->and($slotSet->values->firstWhere('setting_key', 'reps')?->actual_value_type)->toBeNull();

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->call('markEditSetPending', $slotSet->id)
        ->call('saveExerciseEdits');

    $slotSet = $slotSet->fresh('values');

    expect($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Skipped)
        ->and($slotSet->skipped_at)->not->toBeNull();

    $component->call('markExerciseCompleted', $slotExercise->id);

    $slotSet = $slotSet->fresh('values');

    expect($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Completed)
        ->and($slotSet->skipped_at)->toBeNull()
        ->and(TrainingRevisionBatch::query()
            ->where('domain', 'state')
            ->where('action', 'mark_set_skipped')
            ->exists())->toBeTrue()
        ->and(TrainingRevisionBatch::query()
            ->where('domain', 'state')
            ->where('action', 'mark_set_pending')
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', \App\Models\Training\TrainingProgramSlotSet::class)
            ->where('subject_id', $slotSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Skipped->value)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', \App\Models\Training\TrainingProgramSlotSet::class)
            ->where('subject_id', $slotSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Pending->value)
            ->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('treats mark done as skip exercise when every set is drafted as skipped', function () {
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
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
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
    $sets = $slotExercise->sets->sortBy('set_number')->values();

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->call('markEditSetSkipped', $sets[0]->id)
        ->call('markEditSetSkipped', $sets[1]->id)
        ->call('saveExerciseEdits')
        ->call('markExerciseCompleted', $slotExercise->id);

    $slot = $slot->fresh('exercises.sets');
    $slotExercise = $slot->exercises->first();

    expect($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Skipped)
        ->and($slotExercise->skipped_set_count)->toBe(2)
        ->and($slotExercise->completed_set_count)->toBe(0)
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::Skipped)
        ->and($slot->skipped_exercise_count)->toBe(1);

    CarbonImmutable::setTestNow();
});

it('stores an explicit actual value without marking it modified when an athlete saves the planned values', function () {
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

    $batch = TrainingRevisionBatch::query()->latest('id')->first();
    $revision = TrainingActualValueRevision::query()->latest('id')->first();

    expect($value->actual_value_type)->toBe('string')
        ->and($value->actual_string_value)->toBe('6')
        ->and($value->actual_is_explicit)->toBeTrue()
        ->and($value->actual_recorded_by)->toBe($athlete->id)
        ->and($value->is_modified)->toBeFalse()
        ->and($batch?->domain)->toBe('actual')
        ->and($revision?->training_program_slot_set_value_id)->toBe($value->id)
        ->and($revision?->was_explicit)->toBeFalse()
        ->and($revision?->is_explicit)->toBeTrue()
        ->and($revision?->is_modified_from_plan)->toBeFalse();

    CarbonImmutable::setTestNow();
});

it('uses the same base and override cell colors as the admin grid in athlete program details', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Intervals',
        'config' => [
            'settings' => ['reps', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Interval', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'heartRateZone' => ['default' => 1, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $set = $slotExercise->sets->first();

    $set->values->firstWhere('setting_key', 'reps')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '8',
        'is_modified' => true,
    ]);

    $set->values->firstWhere('setting_key', 'heartRateZone')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '2',
        'is_modified' => false,
    ]);

    $slotExercise = $slotExercise->fresh('exercise', 'sets.values');
    $viewData = app(ProgramDetailsExerciseViewBuilder::class)->build($slotExercise, 0);

    expect($viewData->sessionRows[0]->labelClass)->toBe('bg-blue-100 dark:bg-blue-900/20')
        ->and($viewData->sessionRows[0]->valueClasses[0])->toBe('bg-blue-200 dark:bg-blue-700/40')
        ->and($viewData->sessionRows[1]->labelClass)->toBe('bg-green-100 dark:bg-green-900/20')
        ->and($viewData->sessionRows[1]->valueClasses[0])->toBe('bg-yellow-100 dark:bg-yellow-800/40')
        ->and($viewData->sessionRows[0]->valueClasses[0])->not->toContain('ring-amber')
        ->and($viewData->sessionRows[1]->valueClasses[0])->not->toContain('ring-amber');
});

it('renders the same athlete exercise view data from slot models and scheduled snapshots', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Intervals',
        'config' => [
            'settings' => ['reps', 'duration', 'heartRateZone', 'note'],
            'sets' => ['default' => 2, 'label' => 'Interval', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'duration' => ['unit' => 'mm:ss', 'default' => 90, 'applyPer' => 'session'],
            'heartRateZone' => ['default' => 2, 'applyPer' => 'session'],
            'note' => ['default' => 'Strong finish', 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
        'group' => 'A1',
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $firstSet = $slotExercise->sets->sortBy('set_number')->first();

    $firstSet->values->firstWhere('setting_key', 'reps')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '8',
        'is_modified' => true,
    ]);

    $firstSet->values->firstWhere('setting_key', 'duration')->update([
        'actual_value_type' => 'int',
        'actual_int_value' => 105,
        'is_modified' => true,
    ]);

    $firstSet->values->firstWhere('setting_key', 'heartRateZone')->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '3',
        'is_modified' => false,
    ]);

    $slotExercise = $slotExercise->fresh('exercise.equipment', 'exercise.modifiers', 'exercise.media', 'sets.values');
    $snapshot = app(ScheduledSessionSnapshotBuilder::class)->build($slot->fresh());
    $snapshotExercise = collect($snapshot->exercises)->firstWhere('slotExerciseId', $slotExercise->id);

    $fromSlot = app(ProgramDetailsExerciseViewBuilder::class)->build($slotExercise, 0, 'A1');
    $fromSnapshot = app(ProgramDetailsExerciseViewBuilder::class)->buildFromSnapshot($snapshotExercise, 0, 'A1');

    expect($snapshotExercise)->not->toBeNull()
        ->and($snapshotExercise->settingConfigs['duration']['unit'] ?? null)->toBe('mm:ss')
        ->and((string) ($snapshotExercise->settingConfigs['heartRateZone']['default'] ?? ''))->toBe('2')
        ->and($fromSnapshot->toArray())->toBe($fromSlot->toArray());

    CarbonImmutable::setTestNow();
});

it('uses the effective scheduled exercise config for athlete editor fields and snapshots', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Bike Session']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Bike Recovery',
        'config' => [
            'settings' => ['duration'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'duration' => ['unit' => 'seconds', 'default' => 60, 'applyPer' => 'session'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sets = \App\Data\Exercise\Settings\SetsSetting::from([
        'default' => 1,
        'label' => 'Round',
        'deload' => 'none',
    ]);
    $overrides->duration = \App\Data\Exercise\Settings\DurationSetting::from([
        'unit' => 'minutes',
        'default' => 15,
        'applyPer' => 'session',
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('trainingProgram.program.exercises', 'exercises.exercise', 'exercises.sets.values');

    $slotExercise = $slot->exercises->first();

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id);

    $instance = $component->instance();
    $durationField = collect($instance->editSetPanels[0]['fields'])->firstWhere('name', 'duration');
    $snapshot = app(ScheduledSessionSnapshotBuilder::class)->build($slot->fresh());
    $snapshotExercise = collect($snapshot->exercises)->firstWhere('slotExerciseId', $slotExercise->id);

    expect($durationField)->not->toBeNull()
        ->and($durationField->resolveUnit())->toBe('minutes')
        ->and($durationField->resolveSuffix())->toBe('m')
        ->and($instance->editSetTabs[0]['label'])->toBe('Round 1')
        ->and($snapshotExercise)->not->toBeNull()
        ->and($snapshotExercise->setLabel)->toBe('Round')
        ->and($snapshotExercise->settingConfigs['duration']['unit'] ?? null)->toBe('minutes');
});

it('allows recording future program exercises when can_edit_all is enabled', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.can_edit_all', true);

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

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-04 09:00:00'),
    ])->fresh('exercises');

    $slotExercise = $slot->exercises->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-04',
            'trainingProgram' => $trainingProgram,
        ])
        ->assertSee('Mark Done')
        ->assertSee('Skip')
        ->call('markExerciseCompleted', $slotExercise->id);

    expect($slotExercise->fresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed);

    CarbonImmutable::setTestNow();
});

it('lets granular program exercise editability override can_edit_all', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.can_edit_all', true);
    config()->set('athlete.editability.programs.exercises.today', false);

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

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises');

    $slotExercise = $slot->exercises->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->assertDontSee('Mark Done')
        ->assertDontSee('Skip')
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertForbidden();

    expect($slotExercise->fresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending);

    CarbonImmutable::setTestNow();
});
