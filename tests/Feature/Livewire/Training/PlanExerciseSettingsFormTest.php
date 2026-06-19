<?php

use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Livewire\Training\View\PlanExerciseSettingsForm;
use Livewire\Livewire;

it('uses field labels in validation messages for exercise settings', function () {
    $component = Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Split Squat',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 10],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->set('data.config.reps.default', '7/9')
        ->call('submit')
        ->assertHasErrors(['data.config.reps.default' => 'regex'])
        ->assertSee('The default reps field format is invalid.');

    expect($component->instance()->getErrorBag()->first('data.config.reps.default'))
        ->toBe('The default reps field format is invalid.');
});

it('opens exercise-specific session grouping fields in the plan exercise settings form', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Split Squat',
            'focusField' => 'session_grouping',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 10],
                'preview' => [
                    'weeks' => 1,
                    'sessionsPerWeek' => 1,
                    'groupingMode' => 'week',
                    'groupSize' => 1,
                    'copyValuesAutomatically' => true,
                ],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->assertSet('data.config.preview.groupingMode', 'week')
        ->assertSet('data.config.preview.groupSize', 1)
        ->assertSee('Grouping');
});

it('switches to the first settings tab with validation errors when saving drop-set settings', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Goblet Squat',
            'config' => [
                'settings' => ['reps', 'weight', 'tempo', 'rest'],
                'sets' => ['type' => 'drop', 'default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => '10,10,10'],
                'weight' => ['mode' => 'manual', 'default' => 5],
                'tempo' => ['default' => '3010'],
                'rest' => ['default' => 60],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->set('activeFieldsetTab', 'reps')
        ->call('submit')
        ->assertHasErrors(['data.config.weight.default' => 'regex'])
        ->assertSet('activeFieldsetTab', 'weight')
        ->assertSee('The default weight field format is invalid.');
});

it('requires drop-set weight defaults to match the reps part count', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Goblet Squat',
            'config' => [
                'settings' => ['reps', 'weight', 'tempo', 'rest'],
                'sets' => ['type' => 'drop', 'default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => '3x12'],
                'weight' => ['mode' => 'manual', 'default' => '12,12'],
                'tempo' => ['default' => '3010'],
                'rest' => ['default' => 60],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->set('activeFieldsetTab', 'reps')
        ->call('submit')
        ->assertHasErrors(['data.config.weight.default'])
        ->assertSet('activeFieldsetTab', 'weight')
        ->assertSee('The default weight field must have 3 drop-set parts.');
});

it('binds plan exercise settings tabs to the active validation tab state', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Goblet Squat',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['type' => 'drop', 'default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['default' => '10,10,10'],
                'weight' => ['default' => '6,5,4'],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->assertSeeHtml('wire:model.live="activeFieldsetTab"');
});

it('validates empty exercise-specific session grouping group size', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Split Squat',
            'focusField' => 'session_grouping',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 10],
                'preview' => [
                    'weeks' => 1,
                    'sessionsPerWeek' => 1,
                    'groupingMode' => SessionGroupingMode::Groups->value,
                    'groupSize' => 2,
                    'copyValuesAutomatically' => true,
                ],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->set('data.config.preview.groupSize', '')
        ->call('submit')
        ->assertHasErrors(['data.config.preview.groupSize' => 'required']);
});

it('normalizes none exercise-specific session grouping before dispatching settings', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Split Squat',
            'focusField' => 'session_grouping',
            'config' => [
                'settings' => ['reps'],
                'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'manual', 'default' => 10],
                'preview' => [
                    'weeks' => 1,
                    'sessionsPerWeek' => 1,
                    'groupingMode' => SessionGroupingMode::Groups->value,
                    'groupSize' => 2,
                    'copyValuesAutomatically' => true,
                ],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->set('data.config.preview.groupingMode', SessionGroupingMode::None->value)
        ->set('data.config.preview.groupSize', '')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('plan-exercise-settings.saved', function ($event, $params) {
            return ($params['data']['config']['preview']['groupingMode'] ?? null) === SessionGroupingMode::None->value
                && ($params['data']['config']['preview']['groupSize'] ?? null) === 1;
        });
});

it('forces hidden automatic modes back to manual when saving drop-set settings', function () {
    Livewire::test(PlanExerciseSettingsForm::class)
        ->call('openForExercise', [
            'exerciseId' => 1,
            'programExerciseId' => 1,
            'exerciseName' => 'Drop Set Squat',
            'config' => [
                'settings' => ['reps', 'weight'],
                'sets' => ['type' => 'drop', 'default' => 1, 'label' => 'Set', 'deload' => 'none'],
                'reps' => ['mode' => 'automatic', 'default' => '3x12'],
                'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 90, 'default' => '6,5,4'],
                'preview' => ['weeks' => 1, 'sessionsPerWeek' => 1],
                'overrides' => ['sessions' => [], 'cells' => []],
            ],
        ])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('data.config.reps.mode', 'manual')
        ->assertSet('data.config.weight.mode', 'manual')
        ->assertSet('data.config.weight.oneRepMaxModifier', null)
        ->assertDispatched('plan-exercise-settings.saved');
});
