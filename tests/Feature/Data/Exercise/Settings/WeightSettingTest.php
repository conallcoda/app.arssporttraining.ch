<?php

use App\Data\Exercise\Settings\WeightSetting;
use App\Data\Training\Config\ExerciseOverrides;

it('treats blank manual defaults as unspecified', function () {
    $setting = WeightSetting::from([
        'mode' => 'manual',
        'default' => '',
    ]);

    expect($setting->default)->toBeNull()
        ->and($setting->badges())->toBeEmpty();
});

it('normalizes blank weight defaults in exercise overrides', function () {
    $overrides = ExerciseOverrides::from([
        'weight' => [
            'mode' => 'manual',
            'default' => '',
        ],
    ]);

    expect($overrides->weight)->toBeInstanceOf(WeightSetting::class)
        ->and($overrides->weight->default)->toBeNull();
});
