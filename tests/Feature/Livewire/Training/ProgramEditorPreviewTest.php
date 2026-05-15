<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Livewire\Athlete\DaySchedule;
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

it('shows the athlete preview button in the settings panel when an athlete is selected', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
    ])->assertSee('Preview');
});

it('does not show the athlete preview button without a selected athlete', function () {
    $coach = User::factory()->coach()->create();
    $program = ExerciseProgram::factory()->create();

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
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

    TrainingProgramSlot::factory()->create([
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
        ->call('openPreviewSession', '2026-05-06')
        ->assertSee('Back to Preview')
        ->assertSee('Front Squat')
        ->assertSee('Reps')
        ->assertSee('8');
});

it('creates unique preview slot datetimes when multiple preview sessions share the same date', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $group = UserGroup::query()->create([
        'name' => 'Preview Group',
        'owner_id' => $coach->id,
    ]);
    $group->members()->attach($athlete->id);

    Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'userId' => $athlete->id,
        'showActualValueTabs' => true,
        'weeks' => 1,
        'sessionsPerWeek' => 4,
        'weekSessions' => [4],
        'weekSessionDates' => [[
            '2026-05-19',
            '2026-05-19',
            '2026-05-19',
            '2026-05-19',
        ]],
    ])->call('openPreviewModal');

    $trainingProgram = TrainingProgram::query()->latest('id')->first();

    expect($trainingProgram)->not->toBeNull();

    $datetimes = $trainingProgram->slots()
        ->orderBy('datetime')
        ->pluck('datetime')
        ->map(fn ($datetime) => \Carbon\Carbon::parse($datetime)->format('Y-m-d H:i:s'))
        ->all();

    expect($datetimes)->toBe([
        '2026-05-19 08:00:00',
        '2026-05-19 11:00:00',
        '2026-05-19 14:00:00',
        '2026-05-19 17:00:00',
    ]);
});

it('uses the current scheduled training program when previewing with a stale exercise-program parent', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create([
        'parent_type' => TrainingProgram::class,
        'parent_id' => 999999,
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

    $component = Livewire::actingAs($coach)->test(ProgramEditor::class, [
        'exerciseProgram' => $program->fresh(),
        'planId' => $program->id,
        'userId' => $athlete->id,
        'scheduledTrainingProgramId' => $trainingProgram->id,
        'showActualValueTabs' => true,
        'weeks' => 1,
        'sessionsPerWeek' => 1,
        'weekSessions' => [1],
        'weekSessionDates' => [['2026-05-19']],
    ])->call('openPreviewModal');

    $previewTrainingProgram = TrainingProgram::query()
        ->find($component->instance()->previewTrainingProgramId);

    expect($previewTrainingProgram)->not->toBeNull()
        ->and($previewTrainingProgram->group_id)->toBe($group->id);
});
