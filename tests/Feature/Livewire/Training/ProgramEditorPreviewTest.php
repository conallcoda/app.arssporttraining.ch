<?php

use App\Livewire\Athlete\DaySchedule;
use App\Livewire\Training\View\PlanExerciseGrid;
use App\Livewire\Training\View\ProgramEditor;
use App\Livewire\Training\View\ProgramRecordEditor;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the athlete preview button in the settings panel for a scheduled athlete program', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();
    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
    ])->assertSee('Preview');
});

it('remembers the selected plan and actual display mode across program editors', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $firstProgram = ExerciseProgram::factory()->create();
    $secondProgram = ExerciseProgram::factory()->create();

    $first = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $firstProgram,
        'planId' => $firstProgram->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
    ]);

    $first
        ->assertSet('valueDisplayMode', 'planned')
        ->set('valueDisplayMode', 'actual')
        ->assertSet('valueDisplayMode', 'actual');

    expect(session(ProgramEditor::VALUE_DISPLAY_MODE_SESSION_KEY))->toBe('actual');

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $secondProgram,
        'planId' => $secondProgram->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
    ])->assertSet('valueDisplayMode', 'actual');
});

it('does not show the athlete preview button without a selected athlete and scheduled program', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();
    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'showActualValueTabs' => true,
    ])->assertDontSee('Preview');

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
    ])->assertDontSee('Preview');
});

it('bumps the parent grid render version when exercise overrides change', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showActualValueTabs' => true,
    ]);

    expect($component->instance()->gridRenderVersion)->toBe(0);

    $component->call('onExerciseOverridesChanged');

    expect($component->instance()->gridRenderVersion)->toBe(1);
});

it('refreshes parent config without remounting grids when exercise content overrides change', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'showActualValueTabs' => true,
    ]);

    $originalConfig = $component->instance()->planConfigArray;

    $programConfig = $program->fresh()->config;
    $programConfig->setSectionInstructions('main', 'Refreshed parent config');
    $program->config = $programConfig;
    $program->save();

    $component->call('onExerciseContentOverridesChanged');

    expect($component->instance()->gridRenderVersion)->toBe(0)
        ->and($component->instance()->planConfigArray)->not->toBe($originalConfig)
        ->and($component->instance()->planConfigArray['sectionInstructions']['main'] ?? null)->toBe('Refreshed parent config');
});

it('renders program preview sessions and opens athlete-style session details', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $category = Tag::factory()->withScope('exercise_category')->create([
        'name' => 'Strength',
        'short_name' => 'STR',
        'color' => '#22c55e',
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'rest' => ['default' => 90, 'applyPer' => 'session'],
            'overrides' => ['sessions' => [], 'cells' => []],
        ],
    ]);

    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $category->id,
    ]);

    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $group->members()->attach($athlete->id);

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
        'datetime' => '2026-05-06 08:00:00',
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-08 08:00:00',
    ]);

    Livewire::actingAs($coach)->test(DaySchedule::class, [
        'date' => '2026-05-06',
        'showReadiness' => false,
        'previewMode' => true,
        'previewTrainingProgramId' => $trainingProgram->id,
        'previewUserId' => $athlete->id,
    ])
        ->assertSee('08:00')
        ->call('openPreviewSession', (string) $slot->id)
        ->assertSee('Back to Preview')
        ->assertSee('Front Squat')
        ->assertSee('Reps')
        ->assertSee('8');
});

it('opens an exercise session preview from the plan grid using the real scheduled slot id', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $exercise = Exercise::factory()->create(['name' => 'Trap Bar Deadlift']);
    $program = ExerciseProgram::factory()->create();
    $programExercise = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 2,
        'type' => 'main',
    ]);
    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-20 08:00:00',
    ]);

    Livewire::actingAs($coach)->test(PlanExerciseGrid::class, [
        'planId' => $program->id,
        'programExerciseId' => $programExercise->id,
        'exerciseId' => $exercise->id,
        'userId' => $athlete->id,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-05-20']],
        'exerciseName' => $exercise->name,
        'exerciseConfigArray' => $exercise->config->toArray(),
        'programExerciseSort' => 2,
        'programExerciseType' => 'main',
    ])
        ->call('previewSession', 0, 0)
        ->assertDispatched(
            'open-program-preview-at-session',
            sessionKey: (string) $slot->id,
            section: 'main',
            exerciseId: $exercise->id,
            exerciseSort: 2,
        );
});

it('opens the existing athlete editor without the full preview for any coach', function () {
    config()->set('athlete.dashboard_today_override', '03.04.2030');

    $groupOwner = User::factory()->coach()->create();
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'set'],
        ],
    ]);
    $programExercise = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $group = UserGroup::query()->create([
        'name' => 'Recording Group',
        'owner_id' => $groupOwner->id,
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
    ]);

    $component = Livewire::actingAs($coach)->test(ProgramRecordEditor::class, [
        'exerciseProgram' => $program,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'userId' => $athlete->id,
    ])
        ->call('openAtSession', (string) $slot->id, 'main', $exercise->id, 0)
        ->assertSet('open', true)
        ->assertSet('sessionKey', (string) $slot->id)
        ->assertSet('section', 'main')
        ->assertSet('exerciseId', $exercise->id)
        ->assertSee('Edit')
        ->assertSee('Front Squat')
        ->assertSee('Save');

    $component
        ->call('closeEditor', true, $trainingProgram->id, $programExercise->id)
        ->assertSet('open', true)
        ->call('flyoutClosed')
        ->assertSet('open', false)
        ->assertDispatched(
            'training-session-record-updated',
            trainingProgramId: $trainingProgram->id,
            programExerciseId: $programExercise->id,
        );
});

it('opens preview using the existing scheduled training program without creating preview rows', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $group->members()->attach($athlete->id);

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-19 08:00:00',
    ]);

    $programCount = TrainingProgram::query()->count();
    $slotCount = TrainingProgramSlot::query()->count();

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-05-19']],
    ])->call('openPreviewModal');

    expect($component->instance()->previewTrainingProgramId)->toBe($trainingProgram->id)
        ->and(TrainingProgram::query()->count())->toBe($programCount)
        ->and(TrainingProgramSlot::query()->count())->toBe($slotCount);
});

it('renders the existing scheduled slots in preview mode', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $category = Tag::factory()->withScope('exercise_category')->create([
        'name' => 'Strength',
        'short_name' => 'STR',
        'color' => '#22c55e',
    ]);
    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $category->id,
    ]);

    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $group->members()->attach($athlete->id);

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-19 08:00:00',
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-26 08:00:00',
    ]);

    Livewire::actingAs($coach)->test(DaySchedule::class, [
        'date' => '2026-05-19',
        'showReadiness' => false,
        'previewMode' => true,
        'previewTrainingProgramId' => $trainingProgram->id,
        'previewUserId' => $athlete->id,
    ])
        ->assertSee('08:00')
        ->assertSee('Strength');
});
