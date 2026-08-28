<?php

use App\Data\Exercise\Settings\DurationSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Livewire\Athlete\ProgramDetails;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingActualValueRevision;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\Athlete\ProgramDetailsExerciseViewBuilder;
use App\Support\Training\ScheduledSessionSnapshotBuilder;
use App\Training\TrainingSessionProgressService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    if (Schema::hasTable('media')) {
        return;
    }

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->morphs('model');
        $table->uuid('uuid')->nullable()->unique();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    });
});

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

it('shows formatted split reps without a bilateral hint on the athlete dashboard', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Split Squat',
        'instructions' => 'Keep the hips level.',
        'config' => [
            'settings' => ['reps'],
            'sets' => [
                'default' => 1,
                'label' => 'Set',
                'deload' => 'none',
            ],
            'reps' => [
                'mode' => 'manual',
                'default' => '6_6',
                'bilateralExecution' => 'alternating',
                'applyPer' => 'session',
            ],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ]);

    $this->actingAs($athlete)
        ->get('/programs/2026-04-03/'.$trainingProgram->id)
        ->assertOk()
        ->assertSee('Keep the hips level.')
        ->assertSee('6L_6R')
        ->assertDontSee('Alternate sides each rep, for example 1 left, 1 right, repeat until both sides are complete.');
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

it('shows the materialized main section when the warm up is disabled for the athlete', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $warmUpPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Jog Prep'])->id,
        'sort' => 0,
        'type' => 'warm_up',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Front Squat'])->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $config->setUserExerciseOverrides(
        $athlete->id,
        $warmUpPivot->id,
        ExerciseOverrides::from(['disabled' => true]),
    );
    $program->config = $config;
    $program->saveQuietly();

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-03 09:00:00'),
    ])->fresh('exercises');

    expect($slot->exercises->pluck('type')->all())->toBe(['main']);

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2026-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->assertSet('activeSection', 'main')
        ->assertSee('Front Squat')
        ->assertDontSee('No exercises are available for this program.');

    Livewire::actingAs($coach)
        ->test(ProgramDetails::class, [
            'date' => '2026-04-03',
            'trainingProgram' => $trainingProgram,
            'previewMode' => true,
            'previewUserId' => $athlete->id,
            'previewSlotId' => $slot->id,
        ])
        ->assertSet('activeSection', 'main')
        ->assertSee('Front Squat')
        ->assertDontSee('No exercises are available for this program.');
});

it('shows active section instructions above the first exercise', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2026');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $config = $program->config;
    $config->setSectionInstructions('warm_up', 'Warm-up section instructions.');
    $config->setSectionInstructions('main', 'Main section instructions.');
    $program->config = $config;
    $program->saveQuietly();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Jog Prep'])->id,
        'sort' => 0,
        'type' => 'warm_up',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Front Squat'])->id,
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
        ->assertSee('Warm-up section instructions.')
        ->assertDontSee('Main section instructions.')
        ->set('activeSection', 'main')
        ->assertSee('Main section instructions.')
        ->assertDontSee('Warm-up section instructions.');
});

it('lets any coach record through the athlete flow while preserving coach audit attribution', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $groupOwner = User::factory()->coach()->create();
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Delegated Recording', 'owner_id' => $groupOwner->id]);
    $program = ExerciseProgram::factory()->create();
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create([
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'set'],
            ],
        ])->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
    ])->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();

    Livewire::actingAs($coach)->test(ProgramDetails::class, [
        'date' => '2030-04-03',
        'trainingProgram' => $trainingProgram,
        'previewMode' => true,
        'recordMode' => true,
        'previewUserId' => $athlete->id,
        'previewSlotId' => $slot->id,
    ])
        ->assertSet('canRecordSession', true)
        ->call('markExerciseCompleted', $slotExercise->id);

    $batch = TrainingRevisionBatch::query()->where('action', 'mark_exercise_completed')->latest('id')->first();

    expect($slotExercise->refresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($batch?->changed_by)->toBe($coach->id)
        ->and($batch?->source)->toBe('coach');
});

it('opens the delegated compact editor with current values and audits admin changes', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.allow_athlete_edits', true);

    $groupOwner = User::factory()->coach()->create();
    $admin = User::factory()->admin()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Compact Editing', 'owner_id' => $groupOwner->id]);
    $program = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'set'],
        ],
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
    ])->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $value = $slotSet->values->firstWhere('setting_key', 'reps');
    $value->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '6',
        'actual_recorded_by' => $athlete->id,
        'actual_recorded_at' => now(),
        'actual_source' => 'athlete',
        'actual_is_explicit' => true,
    ]);

    $snapshotBuilder = Mockery::mock(ScheduledSessionSnapshotBuilder::class);
    $snapshotBuilder->shouldNotReceive('build');
    app()->instance(ScheduledSessionSnapshotBuilder::class, $snapshotBuilder);

    Livewire::actingAs($admin)->test(ProgramDetails::class, [
        'date' => '2030-04-03',
        'trainingProgram' => $trainingProgram,
        'previewMode' => true,
        'recordMode' => true,
        'editorOnly' => true,
        'previewUserId' => $athlete->id,
        'previewSlotId' => $slot->id,
        'initialSection' => 'main',
        'initialExerciseId' => $exercise->id,
        'initialExerciseSort' => 0,
    ])
        ->assertSet('editingExerciseId', $slotExercise->id)
        ->assertSet("editValues.{$slotSet->id}.reps", '6')
        ->assertSee('Front Squat')
        ->assertSee('Save')
        ->assertDontSee('Mark Done')
        ->set("editValues.{$slotSet->id}.reps", '7')
        ->call('saveExerciseEdits')
        ->assertDispatched(
            'delegated-exercise-editor-closed',
            saved: true,
            trainingProgramId: $trainingProgram->id,
            programExerciseId: $slotExercise->exercise_program_exercise_id,
        )
        ->assertNotDispatched('training-session-record-updated')
        ->assertSet('editingExerciseId', $slotExercise->id);

    $revision = TrainingActualValueRevision::query()
        ->where('training_program_slot_set_value_id', $value->id)
        ->latest('id')
        ->firstOrFail();
    $batch = TrainingRevisionBatch::query()->findOrFail($revision->batch_id);

    expect($value->refresh()->actual_string_value)->toBe('7')
        ->and($value->actual_recorded_by)->toBe($admin->id)
        ->and($value->actual_source)->toBe('admin')
        ->and($slotSet->refresh()->status)->toBe(TrainingProgramSlotSetStatusEnum::CompletedWithModification)
        ->and($slotExercise->refresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($batch->action)->toBe('record_actuals')
        ->and($batch->changed_by)->toBe($admin->id)
        ->and($batch->source)->toBe('admin')
        ->and($revision->recorded_by)->toBe($admin->id)
        ->and($revision->source)->toBe('admin')
        ->and($revision->before_value_type)->toBe('string')
        ->and($revision->before_string_value)->toBe('6')
        ->and($revision->after_value_type)->toBe('string')
        ->and($revision->after_string_value)->toBe('7');

    app(TrainingSessionProgressService::class)->markExerciseSkipped($slotExercise->fresh(['slot', 'sets.values']));

    Livewire::actingAs($admin)->test(ProgramDetails::class, [
        'date' => '2030-04-03',
        'trainingProgram' => $trainingProgram,
        'previewMode' => true,
        'recordMode' => true,
        'editorOnly' => true,
        'previewUserId' => $athlete->id,
        'previewSlotId' => $slot->id,
        'initialSection' => 'main',
        'initialExerciseId' => $exercise->id,
        'initialExerciseSort' => 0,
    ])
        ->assertSet("editSkippedSets.{$slotSet->id}", true)
        ->call('markEditSetPending', $slotSet->id)
        ->set("editValues.{$slotSet->id}.reps", '9')
        ->call('saveExerciseEdits')
        ->assertDispatched('delegated-exercise-editor-closed', saved: true);

    expect($value->refresh()->actual_string_value)->toBe('9')
        ->and($slotSet->refresh()->status)->toBe(TrainingProgramSlotSetStatusEnum::CompletedWithModification)
        ->and($slotExercise->refresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed);
});

it('does not let an athlete forge delegated athlete recording mode', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $owner = User::factory()->coach()->create();
    $otherAthlete = User::factory()->athlete()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Protected Recording', 'owner_id' => $owner->id]);
    $program = ExerciseProgram::factory()->create();
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
    ])->fresh('exercises');

    Livewire::actingAs($otherAthlete)->test(ProgramDetails::class, [
        'date' => '2030-04-03',
        'trainingProgram' => $trainingProgram,
        'previewMode' => true,
        'recordMode' => true,
        'previewUserId' => $athlete->id,
        'previewSlotId' => $slot->id,
    ])
        ->call('markExerciseCompleted', $slot->exercises->first()->id)
        ->assertForbidden();
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

it('materializes planned values as actuals when marking an exercise done', function () {
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
            'name' => 'Goblet Squat',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
                'weight' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'set'],
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

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertDispatched('athlete-exercise-action-succeeded');

    $values = $slotExercise->fresh('sets.values')->sets->first()->values;
    $reps = $values->firstWhere('setting_key', 'reps');
    $weight = $values->firstWhere('setting_key', 'weight');

    expect($reps->actual_value_type)->toBe('string')
        ->and($reps->actual_string_value)->toBe('12')
        ->and($reps->actual_is_explicit)->toBeTrue()
        ->and($reps->actual_source)->toBe('athlete')
        ->and($weight->actual_value_type)->toBe('decimal')
        ->and((float) $weight->actual_decimal_value)->toBe(5.0)
        ->and($weight->actual_is_explicit)->toBeTrue()
        ->and($weight->actual_source)->toBe('athlete');

    CarbonImmutable::setTestNow();
});

it('marks every exercise in the active athlete section done', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseConfig = [
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
    ];

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Drop Jump', 'config' => $exerciseConfig])->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Box Jump', 'config' => $exerciseConfig])->id,
        'sort' => 1,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets');

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->assertSee('Mark All Done')
        ->call('markActiveSectionCompleted')
        ->assertDispatched('athlete-section-action-succeeded')
        ->assertDontSee('Mark All Done')
        ->assertSee('Mark All Pending')
        ->assertSee('Mark All Skipped');

    $slot = $slot->fresh('exercises.sets');

    expect($slot->exercises)
        ->each(fn ($exercise) => $exercise->status->toBe(TrainingProgramSlotExerciseStatusEnum::Completed))
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::Completed)
        ->and($slot->completed_exercise_count)->toBe(2);

    CarbonImmutable::setTestNow();
});

it('marks every pending exercise in the active athlete section skipped', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseConfig = [
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
    ];

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Jog Prep', 'config' => $exerciseConfig])->id,
        'sort' => 0,
        'type' => 'warm_up',
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Drop Jump', 'config' => $exerciseConfig])->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Box Jump', 'config' => $exerciseConfig])->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets');

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->set('activeSection', 'main')
        ->assertSee('Mark All Skipped')
        ->call('markActiveSectionSkipped')
        ->assertDispatched('athlete-section-action-succeeded')
        ->assertDontSee('Mark All Skipped')
        ->assertSee('Mark All Done')
        ->assertSee('Mark All Pending');

    $slot = $slot->fresh('exercises.sets');
    $mainExercises = $slot->exercises->where('type', 'main');
    $warmUpExercise = $slot->exercises->firstWhere('type', 'warm_up');

    expect($mainExercises)
        ->each(fn ($exercise) => $exercise->status->toBe(TrainingProgramSlotExerciseStatusEnum::Skipped))
        ->and($warmUpExercise?->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending)
        ->and($slot->skipped_exercise_count)->toBe(2)
        ->and($slot->pending_exercise_count)->toBe(1);

    CarbonImmutable::setTestNow();
});

it('lets an athlete bulk change a skipped active section to done or pending', function () {
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseConfig = [
        'settings' => ['reps'],
        'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
        'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
    ];

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Drop Jump', 'config' => $exerciseConfig])->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create(['name' => 'Box Jump', 'config' => $exerciseConfig])->id,
        'sort' => 1,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets');

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markActiveSectionSkipped')
        ->assertSee('Mark All Done')
        ->assertSee('Mark All Pending');

    $component
        ->call('markActiveSectionCompleted')
        ->assertDispatched('athlete-section-action-succeeded')
        ->assertSee('Mark All Pending')
        ->assertSee('Mark All Skipped');

    $slot = $slot->fresh('exercises.sets');

    expect($slot->exercises)
        ->each(fn ($exercise) => $exercise->status->toBe(TrainingProgramSlotExerciseStatusEnum::Completed))
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::Completed)
        ->and($slot->completed_exercise_count)->toBe(2);

    $component
        ->call('markActiveSectionSkipped')
        ->call('markActiveSectionPending')
        ->assertDispatched('athlete-section-action-succeeded')
        ->assertSee('Mark All Done')
        ->assertSee('Mark All Skipped');

    $slot = $slot->fresh('exercises.sets');

    expect($slot->exercises)
        ->each(fn ($exercise) => $exercise->status->toBe(TrainingProgramSlotExerciseStatusEnum::Pending))
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::Pending)
        ->and($slot->pending_exercise_count)->toBe(2);

    CarbonImmutable::setTestNow();
});

it('requires athlete-entered values before marking an active section done', function () {
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
        ->call('markActiveSectionCompleted')
        ->assertSet('editingExerciseId', $slotExercise->id)
        ->assertSet('activeEditSet', 'set-'.$slotSet->id)
        ->assertNotDispatched('athlete-section-action-succeeded')
        ->assertSee('This field is required.');

    $slotExercise = $slotExercise->fresh('sets.values');

    expect($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending)
        ->and($slotExercise->sets->first()?->status)->toBe(TrainingProgramSlotSetStatusEnum::Pending)
        ->and($slotExercise->sets->first()?->values->firstWhere('setting_key', 'weight')?->actual_value_type)->toBeNull();

    CarbonImmutable::setTestNow();
});

it('marks valid exercises done and opens the first invalid exercise when marking an active section done', function () {
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
            'name' => 'Ready Exercise',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            ],
        ])->id,
        'sort' => 0,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => Exercise::factory()->create([
            'name' => 'Needs Weight',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => null, 'applyPer' => 'session'],
            ],
        ])->id,
        'sort' => 1,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh(['exercises.exercise', 'exercises.sets.values']);

    $readyExercise = $slot->exercises->firstWhere('exercise.name', 'Ready Exercise');
    $invalidExercise = $slot->exercises->firstWhere('exercise.name', 'Needs Weight');
    $invalidSet = $invalidExercise->sets->first();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('markActiveSectionCompleted')
        ->assertDispatched('athlete-section-action-succeeded')
        ->assertSet('editingExerciseId', $invalidExercise->id)
        ->assertSet('editingExerciseName', 'Needs Weight')
        ->assertSet('activeEditSet', 'set-'.$invalidSet->id)
        ->assertSee('This field is required.');

    $slot = $slot->fresh('exercises.sets.values');
    $readyExercise = $slot->exercises->firstWhere('id', $readyExercise->id);
    $invalidExercise = $slot->exercises->firstWhere('id', $invalidExercise->id);

    expect($readyExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($invalidExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending)
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::PartiallyCompleted)
        ->and($slot->completed_exercise_count)->toBe(1)
        ->and($slot->pending_exercise_count)->toBe(1);

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
            ->where('subject_type', TrainingProgramSlotExercise::class)
            ->where('subject_id', $completedExercise->id)
            ->where('after_value', TrainingProgramSlotExerciseStatusEnum::Completed->value)
            ->where('changed_by', $athlete->id)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramSlotExercise::class)
            ->where('subject_id', $skippedExercise->id)
            ->where('after_value', TrainingProgramSlotExerciseStatusEnum::Skipped->value)
            ->where('changed_by', $athlete->id)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramSlotSet::class)
            ->where('subject_id', $completedSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Completed->value)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramSlotSet::class)
            ->where('subject_id', $skippedSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Skipped->value)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramSlot::class)
            ->where('subject_id', $slot->id)
            ->where('after_value', TrainingProgramSlotStatusEnum::Completed->value)
            ->exists())->toBeTrue();

    $completionBatch = TrainingRevisionBatch::query()
        ->where('action', 'mark_exercise_completed')
        ->latest('id')
        ->first();
    $completedSetRevision = TrainingStateRevision::query()
        ->where('batch_id', $completionBatch?->id)
        ->where('subject_type', TrainingProgramSlotSet::class)
        ->where('subject_id', $completedSet->id)
        ->first();
    $completionContext = json_decode($completionBatch?->reason ?? '{}', true);

    expect($completionBatch?->source)->toBe('athlete')
        ->and($completionContext['operation_id'] ?? null)->not->toBeNull()
        ->and($completedSetRevision?->after_payload['values'][0]['actual_value_type'] ?? null)->not->toBeNull()
        ->and($completedSetRevision?->after_payload['values'][0]['actual_recorded_by'] ?? null)->toBe($athlete->id);

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
        ->assertSet('editingExerciseName', 'Front Squat')
        ->assertSee('Front Squat')
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

it('accepts scalar numeric duration values from the athlete edit modal', function () {
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
            'name' => 'Timed Exercise',
            'config' => [
                'settings' => ['duration'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'duration' => ['unit' => 'seconds', 'default' => 45, 'applyPer' => 'session'],
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
        ->call('openExerciseEditor', $slotExercise->id)
        ->set("editValues.{$slotSet->id}.duration", 35)
        ->call('saveExerciseEdits')
        ->assertHasNoErrors();

    $value = $slotSet->fresh('values')->values->firstWhere('setting_key', 'duration');

    expect($value->actual_value_type)->toBe('int')
        ->and($value->actual_int_value)->toBe(35);

    CarbonImmutable::setTestNow();
});

it('requires athletes to record a concrete rep value when planned reps are a range', function () {
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
            'reps' => ['mode' => 'manual', 'default' => '8-10', 'applyPer' => 'session'],
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

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->assertSee('8-10')
        ->call('openExerciseEditor', $slotExercise->id)
        ->assertSet("editValues.{$slotSet->id}.reps", '');

    $editSetPanels = $component->get('editSetPanels');
    $repsField = collect($editSetPanels[0]['fields'])->firstWhere('name', 'reps');

    expect($repsField?->getPlaceholder())->toBe('8-10');

    $component
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertHasErrors("editValues.{$slotSet->id}.reps");

    expect($slotExercise->fresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending);

    $component
        ->set("editValues.{$slotSet->id}.reps", '8-10')
        ->call('saveExerciseEdits')
        ->assertHasErrors("editValues.{$slotSet->id}.reps");

    $component
        ->set("editValues.{$slotSet->id}.reps", '9')
        ->call('saveExerciseEdits')
        ->assertHasNoErrors()
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertHasNoErrors();

    $value = $slotSet->fresh('values')->values->firstWhere('setting_key', 'reps');

    expect($value->actual_value_type)->toBe('string')
        ->and($value->actual_string_value)->toBe('9')
        ->and($value->is_modified)->toBeTrue()
        ->and($slotExercise->fresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed);

    CarbonImmutable::setTestNow();
});

it('validates athlete drop-set actuals against the session part count', function () {
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
        'name' => 'Goblet Squat',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['type' => 'drop', 'default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => '12,12,12', 'applyPer' => 'set'],
            'weight' => ['mode' => 'manual', 'default' => '8,8,8', 'applyPer' => 'set'],
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
    $slotSet = $slotExercise->sets->sortBy('set_number')->first();

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->assertSet("editValues.{$slotSet->id}.reps", '12,12,12')
        ->assertSet("editValues.{$slotSet->id}.weight", '8,8,8');

    $component
        ->set("editValues.{$slotSet->id}.reps", '11,1,1,1')
        ->set("editValues.{$slotSet->id}.weight", '7,7')
        ->call('saveExerciseEdits')
        ->assertHasErrors([
            "editValues.{$slotSet->id}.reps",
            "editValues.{$slotSet->id}.weight",
        ]);

    $values = $slotSet->fresh('values')->values;

    expect($values->firstWhere('setting_key', 'reps')?->actual_value_type)->toBeNull()
        ->and($values->firstWhere('setting_key', 'weight')?->actual_value_type)->toBeNull();

    $component
        ->set("editValues.{$slotSet->id}.reps", '11,11,11')
        ->set("editValues.{$slotSet->id}.weight", '7,7,7')
        ->call('saveExerciseEdits')
        ->assertHasNoErrors();

    $values = $slotSet->fresh('values')->values;

    expect($values->firstWhere('setting_key', 'reps')?->actual_string_value)->toBe('11,11,11')
        ->and($values->firstWhere('setting_key', 'weight')?->actual_string_value)->toBe('7,7,7');

    CarbonImmutable::setTestNow();
});

it('moves the edit modal to the first invalid set when saving incomplete values', function () {
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
            'name' => 'Push up Max',
            'config' => [
                'settings' => ['reps', 'weight', 'rest'],
                'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => '5-8', 'applyPer' => 'session'],
                'weight' => ['mode' => 'manual', 'default' => 0, 'applyPer' => 'session'],
                'rest' => ['default' => 60, 'applyPer' => 'session'],
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
    [$firstSet, $secondSet] = $slotExercise->sets->sortBy('set_number')->values()->all();

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->assertSet('activeEditSet', 'set-'.$firstSet->id)
        ->set("editValues.{$firstSet->id}.reps", '5')
        ->call('saveExerciseEdits')
        ->assertHasErrors("editValues.{$secondSet->id}.reps")
        ->assertSet('activeEditSet', 'set-'.$secondSet->id)
        ->assertDispatched('athlete-exercise-editor-focus', model: "editValues.{$secondSet->id}.reps");

    expect($firstSet->fresh('values')->values->firstWhere('setting_key', 'reps')?->actual_value_type)->toBeNull();

    CarbonImmutable::setTestNow();
});

it('opens the edit modal on the set and field selected from a session value cell', function () {
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
            'name' => 'Plank Side on Roll Max',
            'config' => [
                'settings' => ['weight', 'duration', 'rest'],
                'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
                'weight' => ['mode' => 'manual', 'default' => 0, 'applyPer' => 'session'],
                'duration' => ['unit' => 'seconds', 'default' => 30, 'applyPer' => 'session'],
                'rest' => ['default' => 60, 'applyPer' => 'session'],
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
    $secondSet = $slotExercise->sets->sortBy('set_number')->values()->get(1);

    $component = Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ]);

    $weightRow = collect($component->instance()->programExercises[0]->sessionRows)
        ->firstWhere('settingKey', 'weight');

    expect($weightRow?->setIds[1] ?? null)->toBe($secondSet->id);

    $component
        ->call('openExerciseEditor', $slotExercise->id, $secondSet->id, 'weight')
        ->assertSet('activeEditSet', 'set-'.$secondSet->id)
        ->assertDispatched('athlete-exercise-editor-focus', model: "editValues.{$secondSet->id}.weight");

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
            ->where('subject_type', TrainingProgramSlotSet::class)
            ->where('subject_id', $slotSet->id)
            ->where('after_value', TrainingProgramSlotSetStatusEnum::Skipped->value)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramSlotSet::class)
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
    $actualContext = json_decode($batch?->reason ?? '{}', true);

    expect($value->actual_value_type)->toBe('string')
        ->and($value->actual_string_value)->toBe('6')
        ->and($value->actual_is_explicit)->toBeTrue()
        ->and($value->actual_recorded_by)->toBe($athlete->id)
        ->and($value->is_modified)->toBeFalse()
        ->and($batch?->domain)->toBe('actual')
        ->and($batch?->source)->toBe('athlete')
        ->and($actualContext['operation_id'] ?? null)->not->toBeNull()
        ->and($actualContext['training_program_slot_id'] ?? null)->toBe($slot->id)
        ->and($actualContext['training_program_slot_exercise_id'] ?? null)->toBe($slotExercise->id)
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
    CarbonImmutable::setTestNow('2030-04-03 12:00:00');
    config()->set('athlete.dashboard_today_override', '03.04.2030');
    config()->set('athlete.allow_athlete_edits', true);

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
    $overrides->sets = SetsSetting::from([
        'default' => 1,
        'label' => 'Round',
        'deload' => 'none',
    ]);
    $overrides->duration = DurationSetting::from([
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

    $editSetPanels = $component->get('editSetPanels');
    $editSetTabs = $component->get('editSetTabs');
    $durationField = collect($editSetPanels[0]['fields'])->firstWhere('name', 'duration');
    $snapshot = app(ScheduledSessionSnapshotBuilder::class)->build($slot->fresh());
    $snapshotExercise = collect($snapshot->exercises)->firstWhere('slotExerciseId', $slotExercise->id);

    expect($durationField)->not->toBeNull()
        ->and($durationField->type)->toBe('text')
        ->and($durationField->resolveSuffix())->toBe('m')
        ->and($editSetTabs[0]['label'])->toBe('Round 1')
        ->and($snapshotExercise)->not->toBeNull()
        ->and($snapshotExercise->setLabel)->toBe('Round')
        ->and($snapshotExercise->settingConfigs['duration']['unit'] ?? null)->toBe('minutes');

    CarbonImmutable::setTestNow();
});

it('uses the slot exercise config snapshot when live settings change after materialization', function () {
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
        'name' => 'Goblet squat',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => ['type' => 'normal', 'default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'set'],
            'weight' => ['mode' => 'manual', 'default' => 7.5, 'applyPer' => 'set'],
            'tempo' => ['default' => '3010', 'applyPer' => 'week'],
            'rest' => ['default' => 30, 'applyPer' => 'week'],
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

    $exercise->update([
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => ['type' => 'drop', 'default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => '3x12', 'applyPer' => 'set'],
            'weight' => ['mode' => 'manual', 'default' => '12,12,12', 'applyPer' => 'set'],
            'tempo' => ['default' => '3010', 'applyPer' => 'week'],
            'rest' => ['default' => 30, 'applyPer' => 'week'],
        ],
    ]);

    $viewData = app(ProgramDetailsExerciseViewBuilder::class)->build($slotExercise->fresh('exercise', 'sets.values'), 0, 'A1');
    $repsRow = collect($viewData->sessionRows)->firstWhere('label', 'Reps');
    $weightRow = collect($viewData->sessionRows)->firstWhere('label', 'Weight (kg)');

    expect($slotExercise->settingSnapshot->config['sets']['type'] ?? null)->toBe('normal')
        ->and($repsRow?->values[0] ?? null)->toBe('12')
        ->and($weightRow?->values[0] ?? null)->toBe('7.5');

    Livewire::actingAs($athlete)
        ->test(ProgramDetails::class, [
            'date' => '2030-04-03',
            'trainingProgram' => $trainingProgram,
        ])
        ->call('openExerciseEditor', $slotExercise->id)
        ->assertSet("editValues.{$firstSet->id}.reps", '12')
        ->assertSet("editValues.{$firstSet->id}.weight", 7.5)
        ->call('markExerciseCompleted', $slotExercise->id)
        ->assertHasNoErrors();

    expect($slotExercise->fresh()->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed);

    CarbonImmutable::setTestNow();
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
