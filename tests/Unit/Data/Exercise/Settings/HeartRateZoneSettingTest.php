<?php

use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\Settings\HeartRateZoneSetting;

it('has correct defaults', function () {
    $setting = new HeartRateZoneSetting;

    expect($setting->default)->toBe('2');
    expect($setting->applyPer)->toBe('session');
});

it('returns zone unit label', function () {
    expect(HeartRateZoneSetting::unitLabel())->toBe('zone');
});

it('returns numeric input meta for heart rate zone', function () {
    $meta = HeartRateZoneSetting::inputMeta();

    expect($meta)->toBeInstanceOf(CellInputMeta::class);
    expect($meta->inputType)->toBe('number');
    expect($meta->min)->toBe(0);
    expect($meta->max)->toBe(4);
    expect($meta->inputStep)->toBe('1');
    expect($meta->pattern)->toBeNull();
});

it('returns fields array', function () {
    $fields = HeartRateZoneSetting::fields();

    expect($fields)->toBeArray();
    expect($fields)->not->toBeEmpty();
});
