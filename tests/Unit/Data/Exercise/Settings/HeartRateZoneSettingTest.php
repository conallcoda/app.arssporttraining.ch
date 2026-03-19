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

it('returns text input meta with heart rate zone pattern', function () {
    $meta = HeartRateZoneSetting::inputMeta();

    expect($meta)->toBeInstanceOf(CellInputMeta::class);
    expect($meta->inputType)->toBe('text');
    expect($meta->maxlength)->toBe(3);
    expect($meta->pattern)->toBe('[0-4](-[0-4])?');
});

it('pattern validates single zone values', function (string $value) {
    $pattern = HeartRateZoneSetting::inputMeta()->pattern;
    $regex = '/^'.$pattern.'$/';

    expect(preg_match($regex, $value))->toBe(1);
})->with(['0', '1', '2', '3', '4']);

it('pattern validates zone range values', function (string $value) {
    $pattern = HeartRateZoneSetting::inputMeta()->pattern;
    $regex = '/^'.$pattern.'$/';

    expect(preg_match($regex, $value))->toBe(1);
})->with(['0-1', '0-4', '2-3', '1-4']);

it('pattern rejects invalid zone values', function (string $value) {
    $pattern = HeartRateZoneSetting::inputMeta()->pattern;
    $regex = '/^'.$pattern.'$/';

    expect(preg_match($regex, $value))->toBe(0);
})->with(['5', '6', '1-', '-4', '1-2-3', 'abc', '']);

it('returns fields array', function () {
    $fields = HeartRateZoneSetting::fields();

    expect($fields)->toBeArray();
    expect($fields)->not->toBeEmpty();
});
