<?php

use App\Data\Exercise\ExerciseConfig;
use App\Livewire\Database\ExerciseForm;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTemplate;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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

it('auto-expands a generic preview week when sessions diverge', function () {
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
                'overrides' => ['cells' => [], 'weeks' => []],
            ],
            'template' => null,
            'category' => null,
        ])
        ->call('updateCellOverride', 0, 0, 'reps', 14, 1, false)
        ->assertSet('effectiveExpandedWeeks', [0]);
});
