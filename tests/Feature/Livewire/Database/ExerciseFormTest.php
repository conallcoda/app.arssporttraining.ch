<?php

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\ExerciseData;
use App\Livewire\Database\ExerciseForm;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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

it('applies the category default template when creating a new exercise', function () {
    $template = ExerciseTemplate::create([
        'name' => 'Strength Default',
        'config' => (new ExerciseConfig(settings: ['note']))->toArray(),
    ]);

    $category = Tag::factory()->create([
        'scope' => 'exercise_category',
        'default_exercise_template_id' => $template->id,
    ]);

    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'config' => (new ExerciseConfig)->toArray(),
            'template' => null,
            'category' => null,
        ])
        ->set('data.category', $category->id)
        ->assertSet('data.template', $template->id)
        ->assertSet('data.config.settings', ['note']);
});

it('persists the selected category when creating an exercise', function () {
    $warmUp = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Warm-up',
        'color' => 'amber',
    ]);

    Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Cooldown',
        'color' => 'amber',
    ]);

    $exercise = (new ExerciseData(
        id: null,
        name: 'Dynamic Mobility',
        category: $warmUp->id,
        config: new ExerciseConfig,
    ))->persist();

    expect($exercise->category_id)->toBe($warmUp->id)
        ->and($exercise->category?->name)->toBe('Warm-up');
});

it('switches to the first tab with validation errors when submitting a tabbed exercise modal', function () {
    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'category' => null,
            'config' => [
                ...((new ExerciseConfig)->toArray()),
                'settings' => ['reps'],
                'sets' => ['default' => 5, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
            ],
            'template' => null,
        ])
        ->set('activeFieldsetTab', 'settings')
        ->call('submit')
        ->assertHasErrors(['data.name', 'data.category'])
        ->assertSet('activeFieldsetTab', 'general');
});

it('binds exercise fieldset tabs to the active validation tab state', function () {
    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'category' => null,
            'config' => (new ExerciseConfig)->toArray(),
            'template' => null,
        ])
        ->assertSeeHtml('wire:model.live="activeFieldsetTab"');
});

it('clears validation errors when reopening a fresh exercise modal', function () {
    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'category' => null,
            'config' => (new ExerciseConfig)->toArray(),
            'template' => null,
        ])
        ->call('submit')
        ->assertHasErrors(['data.name', 'data.category'])
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'category' => null,
            'config' => (new ExerciseConfig)->toArray(),
            'template' => null,
        ])
        ->assertHasNoErrors()
        ->assertSet('activeFieldsetTab', 'general');
});

it('applies the root category default template when creating an exercise in a subcategory', function () {
    $template = ExerciseTemplate::create([
        'name' => 'Strength Default',
        'config' => (new ExerciseConfig(settings: ['note']))->toArray(),
    ]);

    $rootCategory = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Strength',
        'default_exercise_template_id' => $template->id,
    ]);

    $subCategory = Tag::factory()->childOf($rootCategory)->create([
        'name' => 'Explosive Strength',
    ]);

    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'config' => (new ExerciseConfig)->toArray(),
            'template' => null,
            'category' => null,
        ])
        ->set('data.category', $subCategory->id)
        ->assertSet('data.template', $template->id)
        ->assertSet('data.config.settings', ['note']);
});

it('does not apply the category default template when editing an exercise', function () {
    $template = ExerciseTemplate::create([
        'name' => 'Strength Default',
        'config' => (new ExerciseConfig(settings: ['note']))->toArray(),
    ]);

    $category = Tag::factory()->create([
        'scope' => 'exercise_category',
        'default_exercise_template_id' => $template->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => (new ExerciseConfig(settings: ['reps']))->toArray(),
    ]);

    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'config' => $exercise->config->toArray(),
            'template' => null,
            'category' => null,
        ])
        ->set('data.category', $category->id)
        ->assertSet('data.template', null)
        ->assertSet('data.config.settings', ['reps']);
});

it('keeps the database form preview constrained to a single session', function () {
    Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => 'Tempo Split Squat',
            'config' => [
                ...((new ExerciseConfig)->toArray()),
                'settings' => ['reps'],
                'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
                'preview' => [
                    'weeks' => 1,
                    'sessionsPerWeek' => 2,
                    'measuredReps' => 1,
                    'measuredWeight' => 50,
                    'targetGoal' => 10,
                ],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
            'template' => null,
            'category' => null,
        ])
        ->call('updateCellOverride', 0, 0, 'reps', 14, 1, false)
        ->assertSet('effectiveExpandedWeeks', [])
        ->assertSet('previewGrid.sessionsPerWeek', 1)
        ->assertSet('previewGrid.weekSessionCounts', [1]);
});

it('does not apply coach session-grouping defaults to database exercise form previews', function () {
    $coach = User::factory()->coach()->create();
    $coach->config->set('settings.session_grouping', [
        'mode' => 'groups',
        'groupSize' => 3,
    ]);
    $coach->save();

    $this->actingAs($coach);

    $component = Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => '',
            'config' => (new ExerciseConfig)->toArray(),
            'template' => null,
            'category' => null,
        ]);

    expect($component->get('data')['config']['preview']['groupingMode'] ?? null)->toBeNull()
        ->and($component->get('data')['config']['preview']['groupSize'] ?? null)->toBeNull()
        ->and($component->instance()->previewGrid->sessionsPerWeek)->toBe(1)
        ->and($component->instance()->previewGrid->weekSessionCounts)->toBe([1]);
});

it('normalizes missing apply-per defaults for exercise settings in the form', function () {
    $component = Livewire::test(ExerciseForm::class)
        ->call('open', data: [
            'id' => null,
            'name' => 'Fixture Exercise',
            'config' => [
                ...((new ExerciseConfig)->toArray()),
                'settings' => ['reps', 'rest', 'distance'],
                'reps' => ['mode' => 'manual', 'default' => 12],
                'rest' => ['default' => 60],
                'distance' => ['unit' => 'meters', 'default' => 500],
            ],
            'template' => null,
            'category' => null,
        ]);

    expect($component->get('data')['config']['reps']['applyPer'] ?? null)->toBe('per_set')
        ->and($component->get('data')['config']['rest']['applyPer'] ?? null)->toBe('per_session')
        ->and($component->get('data')['config']['distance']['applyPer'] ?? null)->toBe('per_set');
});
