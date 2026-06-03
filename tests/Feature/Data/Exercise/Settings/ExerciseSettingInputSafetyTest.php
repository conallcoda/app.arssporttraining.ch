<?php

use App\Data\Exercise\Settings\RestSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\ExerciseConfig;
use App\Data\Training\Config\ExerciseOverrides;
use Coda\FormKit\Field;
use Illuminate\Support\Facades\Validator;

it('hydrates blank form values safely for every exercise setting', function () {
    $overrides = ExerciseOverrides::from([
        'sets' => [
            'default' => '',
            'deloadBy' => '',
        ],
        'distance' => ['default' => ''],
        'duration' => ['default' => ''],
        'heartRate' => ['default' => ''],
        'heartRateZone' => ['default' => ''],
        'pace' => ['default' => ''],
        'reps' => [
            'default' => '',
            'stepDownInterval' => '',
            'decrement' => '',
            'minimum' => '',
        ],
        'rest' => ['default' => ''],
        'tempo' => ['default' => ''],
        'watts' => ['default' => ''],
        'weight' => [
            'default' => '',
            'oneRepMaxModifier' => '',
        ],
    ]);

    expect($overrides->sets->default)->toBe(4)
        ->and($overrides->sets->deloadBy)->toBeNull()
        ->and($overrides->distance->default)->toBeNull()
        ->and($overrides->duration->default)->toBeNull()
        ->and($overrides->heartRate->default)->toBeNull()
        ->and($overrides->heartRateZone->default)->toBeNull()
        ->and($overrides->pace->default)->toBeNull()
        ->and($overrides->reps->default)->toBeNull()
        ->and($overrides->reps->stepDownInterval)->toBeNull()
        ->and($overrides->reps->decrement)->toBeNull()
        ->and($overrides->reps->minimum)->toBeNull()
        ->and($overrides->rest->default)->toBeNull()
        ->and($overrides->tempo->default)->toBeNull()
        ->and($overrides->watts->default)->toBeNull()
        ->and($overrides->weight->default)->toBeNull()
        ->and($overrides->weight->oneRepMaxModifier)->toBeNull();

    $config = ExerciseConfig::from([
        'settings' => ['note'],
        'note' => ['default' => ''],
    ]);

    expect($config->note->default)->toBeNull();
});

it('casts numeric form strings before constructing typed settings', function () {
    $overrides = ExerciseOverrides::from([
        'sets' => ['default' => '3'],
        'rest' => ['default' => '90'],
        'weight' => [
            'default' => '7.5',
            'oneRepMaxModifier' => '95',
        ],
    ]);

    expect($overrides->sets->default)->toBe(3)
        ->and($overrides->rest->default)->toBe(90)
        ->and($overrides->weight->default)->toBe(7.5)
        ->and($overrides->weight->oneRepMaxModifier)->toBe(95);
});

it('allows blank optional defaults but rejects invalid setting values', function () {
    $optionalRules = Field::buildValidationRules(RestSetting::fields(), 'data.config.rest.');

    expect(Validator::make([
        'data' => ['config' => ['rest' => ['default' => '']]],
    ], $optionalRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['rest' => ['default' => 'abc']]],
        ], $optionalRules)->errors()->has('data.config.rest.default'))->toBeTrue();
});

it('requires a valid set count because sets are structural', function () {
    $rules = Field::buildValidationRules(SetsSetting::fields(), 'data.config.sets.');

    expect(Validator::make([
        'data' => ['config' => ['sets' => ['default' => '']]],
    ], $rules)->errors()->has('data.config.sets.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['sets' => ['default' => '0']]],
        ], $rules)->errors()->has('data.config.sets.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['sets' => ['default' => '4']]],
        ], $rules)->passes())->toBeTrue();
});

it('allows rep ranges for planning but not athlete recording', function () {
    $manualPlanningData = [
        'config' => [
            'settings' => ['reps'],
            'reps' => ['mode' => 'manual'],
        ],
    ];
    $automaticRepsData = [
        'config' => [
            'settings' => ['reps'],
            'reps' => ['mode' => 'automatic'],
        ],
    ];
    $automaticWeightData = [
        'config' => [
            'settings' => ['reps', 'weight'],
            'reps' => ['mode' => 'manual'],
            'weight' => ['mode' => 'automatic'],
        ],
    ];
    $planningRules = Field::buildValidationRules(RepsSetting::fields(), 'data.config.reps.', $manualPlanningData);
    $automaticRepsRules = Field::buildValidationRules(RepsSetting::fields(), 'data.config.reps.', $automaticRepsData);
    $automaticWeightRules = Field::buildValidationRules(RepsSetting::fields(), 'data.config.reps.', $automaticWeightData);
    $athleteRules = Field::buildValidationRules([
        RepsSetting::athleteField('reps'),
    ], 'editValues.1.');

    expect(Validator::make([
        'data' => ['config' => ['reps' => ['default' => '8-10']]],
    ], $planningRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '8-10']]],
        ], $automaticRepsRules)->errors()->has('data.config.reps.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '']]],
        ], $automaticRepsRules)->errors()->has('data.config.reps.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '8-10']]],
        ], $automaticWeightRules)->errors()->has('data.config.reps.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '6_6']]],
        ], $automaticWeightRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['reps' => '8-10']],
        ], $athleteRules)->errors()->has('editValues.1.reps'))->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['reps' => '6_6']],
        ], $athleteRules)->passes())->toBeTrue()
        ->and(RepsSetting::athleteCanonicalValue('8-10'))->toMatchArray([
            'kind' => 'reps',
            'format' => 'range',
            'display' => '8-10',
            'min' => 8,
            'max' => 10,
        ])
        ->and(RepsSetting::requiresAthleteSpecificValue('8-10'))->toBeTrue()
        ->and(RepsSetting::requiresAthleteSpecificValue('-'))->toBeTrue()
        ->and(RepsSetting::requiresAthleteSpecificValue('6_6'))->toBeFalse();
});
