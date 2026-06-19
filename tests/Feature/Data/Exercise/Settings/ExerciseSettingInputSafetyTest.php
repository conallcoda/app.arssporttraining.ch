<?php

use App\Data\Exercise\DropSet;
use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Settings\DurationSetting;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Exercise\Settings\RestSetting;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\TempoSetting;
use App\Data\Exercise\Settings\WeightSetting;
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

it('allows four-character tempo values made from digits and x', function () {
    $athleteField = TempoSetting::athleteField('tempo');
    $rules = Field::buildValidationRules(TempoSetting::fields(), 'data.config.tempo.');
    $athleteRules = Field::buildValidationRules([
        $athleteField,
    ], 'editValues.1.');

    expect($athleteField->maxLength)->toBe(4)
        ->and(TempoSetting::inputMeta()->pattern)->toBe('[0-9xX]{4}')
        ->and(TempoSetting::inputMeta()->mask)->toBeNull();

    foreach (['3010', '30X0', 'XXXX', 'X10X', 'xxxx', 'x10x'] as $tempo) {
        expect(Validator::make([
            'data' => ['config' => ['tempo' => ['default' => $tempo]]],
        ], $rules)->passes())->toBeTrue("Expected planning tempo {$tempo} to pass")
            ->and(Validator::make([
                'editValues' => [1 => ['tempo' => $tempo]],
            ], $athleteRules)->passes())->toBeTrue("Expected athlete tempo {$tempo} to pass");
    }

    foreach (['301', '30100', '30A0', '3-10', ''] as $tempo) {
        expect(Validator::make([
            'data' => ['config' => ['tempo' => ['default' => $tempo]]],
        ], $rules)->errors()->has('data.config.tempo.default'))->toBe($tempo !== '')
            ->and(Validator::make([
                'editValues' => [1 => ['tempo' => $tempo]],
            ], $athleteRules)->errors()->has('editValues.1.tempo'))->toBeTrue();
    }
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

it('allows split durations and stores canonical duration metadata', function () {
    $defaultField = collect(DurationSetting::fields())->firstWhere('name', 'default');
    $athleteField = DurationSetting::athleteField('duration', ['unit' => 'mm:ss']);
    $secondsAthleteField = DurationSetting::athleteField('duration', ['unit' => 'seconds']);
    $rules = Field::buildValidationRules(DurationSetting::fields(), 'data.config.duration.');
    $athleteRules = Field::buildValidationRules([
        $athleteField,
    ], 'editValues.1.');
    $secondsAthleteRules = Field::buildValidationRules([
        $secondsAthleteField,
    ], 'editValues.1.');

    expect($defaultField?->type)->toBe('text')
        ->and($defaultField?->inputType)->toBe('text')
        ->and($defaultField?->maxLength)->toBe(15)
        ->and($athleteField->type)->toBe('text')
        ->and($athleteField->inputType)->toBe('text')
        ->and($athleteField->maxLength)->toBe(15)
        ->and(Validator::make([
        'data' => ['config' => ['duration' => ['default' => '10:00_10:00']]],
    ], $rules)->passes())->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['duration' => ['default' => '10:99_10:00']]],
        ], $rules)->errors()->has('data.config.duration.default'))->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['duration' => '10:00_10:00']],
        ], $athleteRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['duration' => 35]],
        ], $secondsAthleteRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['duration' => '35']],
        ], $secondsAthleteRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['duration' => '10:99_10:00']],
        ], $athleteRules)->errors()->has('editValues.1.duration'))->toBeTrue()
        ->and(DurationSetting::normalizeAthleteValue('10:00_10:00', ['unit' => 'mm:ss']))->toBe('600_600')
        ->and(DurationSetting::athleteValueType('600_600', ['unit' => 'mm:ss']))->toBe('string')
        ->and(DurationSetting::athleteCanonicalValue('600_600', ['unit' => 'mm:ss']))->toMatchArray([
            'kind' => 'duration',
            'format' => 'split',
            'display' => '10:00L_10:00R',
            'unit' => 'mm:ss',
            'seconds' => 1200,
            'parts' => [600, 600],
            'is_bilateral' => true,
        ]);
});

it('only allows drop-set syntax when the set type explicitly opts in', function () {
    $normalConfig = [
        'settings' => ['reps', 'weight', 'duration'],
        'sets' => ['type' => DropSet::SET_TYPE_NORMAL],
        'reps' => ['mode' => 'manual'],
        'weight' => ['mode' => 'manual'],
        'duration' => ['unit' => 'mm:ss'],
    ];
    $dropConfig = [
        'settings' => ['reps', 'weight', 'duration'],
        'sets' => ['type' => DropSet::SET_TYPE_DROP],
        'reps' => ['mode' => 'automatic', 'default' => '3x12'],
        'weight' => ['mode' => 'automatic'],
        'duration' => ['unit' => 'mm:ss'],
    ];

    $normalRepsRules = Field::buildValidationRules(RepsSetting::fields(['config' => $normalConfig]), 'data.config.reps.');
    $dropRepsRules = Field::buildValidationRules(RepsSetting::fields(['config' => $dropConfig]), 'data.config.reps.');
    $dropRepsAthleteRules = Field::buildValidationRules([
        RepsSetting::athleteField('reps', $dropConfig),
    ], 'editValues.1.');
    $normalWeightRules = Field::buildValidationRules(WeightSetting::fields(['config' => $normalConfig]), 'data.config.weight.');
    $dropWeightRules = Field::buildValidationRules(WeightSetting::fields(['config' => $dropConfig]), 'data.config.weight.', ['config' => $dropConfig]);
    $dropDurationRules = Field::buildValidationRules(DurationSetting::fields(['config' => $dropConfig]), 'data.config.duration.', ['config' => $dropConfig]);

    expect(collect(RepsSetting::fields(['config' => $dropConfig]))->pluck('name')->all())->not->toContain('mode', 'stepDownInterval', 'decrement', 'minimum')
        ->and(collect(WeightSetting::fields(['config' => $dropConfig]))->pluck('name')->all())->not->toContain('mode', 'oneRepMaxModifier')
        ->and(collect(RepsSetting::fields(['config' => $normalConfig]))->pluck('name')->all())->toContain('mode')
        ->and(collect(WeightSetting::fields(['config' => $normalConfig]))->pluck('name')->all())->toContain('mode', 'oneRepMaxModifier')
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '12,12,12']]],
        ], $normalRepsRules)->errors()->has('data.config.reps.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '12,12,12']]],
        ], $dropRepsRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['reps' => ['default' => '3x12']]],
        ], $dropRepsRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'editValues' => [1 => ['reps' => '12,12']],
        ], $dropRepsAthleteRules)->passes())->toBeTrue()
        ->and(RepsSetting::normalizeAthleteValue('12,12', $dropConfig))->toBe('12,12,0')
        ->and(Validator::make([
            'data' => ['config' => ['weight' => ['default' => '6,5,4']]],
        ], $normalWeightRules)->errors()->has('data.config.weight.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['weight' => ['default' => '6,5,4']]],
        ], $dropWeightRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['weight' => ['default' => '6,5']]],
        ], $dropWeightRules)->errors()->has('data.config.weight.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['duration' => ['default' => '0:30,0:20,0:10']]],
        ], $dropDurationRules)->passes())->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['duration' => ['default' => '0:30,0:20']]],
        ], $dropDurationRules)->errors()->has('data.config.duration.default'))->toBeTrue()
        ->and(Validator::make([
            'data' => ['config' => ['duration' => ['default' => '0:99,0:20']]],
        ], $dropDurationRules)->errors()->has('data.config.duration.default'))->toBeTrue();
});
