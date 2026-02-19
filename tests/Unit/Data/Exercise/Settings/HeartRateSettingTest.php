<?php

use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\Settings\HeartRateSetting;

it('has correct defaults', function () {
    $setting = new HeartRateSetting;

    expect($setting->default)->toBe('140');
    expect($setting->applyPer)->toBe('session');
});

it('returns bpm unit label', function () {
    expect(HeartRateSetting::unitLabel())->toBe('bpm');
});

it('returns text input meta with heart rate pattern', function () {
    $meta = HeartRateSetting::inputMeta();

    expect($meta)->toBeInstanceOf(CellInputMeta::class);
    expect($meta->inputType)->toBe('text');
    expect($meta->maxlength)->toBe(7);
    expect($meta->pattern)->toBe('\d{1,3}(-\d{1,3})?');
});

it('pattern validates single heart rate values', function (string $value) {
    $pattern = HeartRateSetting::inputMeta()->pattern;
    $regex = '/^'.$pattern.'$/';

    expect(preg_match($regex, $value))->toBe(1);
})->with(['1', '60', '140', '200']);

it('pattern validates heart rate range values', function (string $value) {
    $pattern = HeartRateSetting::inputMeta()->pattern;
    $regex = '/^'.$pattern.'$/';

    expect(preg_match($regex, $value))->toBe(1);
})->with(['60-80', '140-170', '1-200']);

it('pattern rejects invalid heart rate values', function (string $value) {
    $pattern = HeartRateSetting::inputMeta()->pattern;
    $regex = '/^'.$pattern.'$/';

    expect(preg_match($regex, $value))->toBe(0);
})->with(['abc', '140-', '-170', '1234', '140-170-180', '']);

it('returns fields array', function () {
    $fields = HeartRateSetting::fields();

    expect($fields)->toBeArray();
    expect($fields)->not->toBeEmpty();
});
