<?php

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
