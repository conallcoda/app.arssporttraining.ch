<?php

use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\Settings\HeartRateSetting;

it('has correct defaults', function () {
    $setting = new HeartRateSetting;

    expect($setting->mode)->toBe('manual');
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

it('returns fields array with mode radio', function () {
    $fields = HeartRateSetting::fields();

    expect($fields)->toBeArray();
    expect($fields)->toHaveCount(3);
});

it('returns auto biking badge when mode is automatic_biking', function () {
    $setting = new HeartRateSetting(mode: 'automatic_biking');

    $badges = $setting->badges();

    expect($badges)->toHaveCount(1);
    expect($badges[0]['label'])->toBe('Auto (Biking)');
});

it('returns auto jogging badge when mode is automatic_jogging', function () {
    $setting = new HeartRateSetting(mode: 'automatic_jogging');

    $badges = $setting->badges();

    expect($badges)->toHaveCount(1);
    expect($badges[0]['label'])->toBe('Auto (Jogging)');
});

it('returns bpm badge when mode is manual', function () {
    $setting = new HeartRateSetting(mode: 'manual', default: '140');

    $badges = $setting->badges();

    expect($badges)->toHaveCount(1);
    expect($badges[0]['label'])->toBe('140 bpm');
});

it('returns empty badges when manual mode with no default', function () {
    $setting = new HeartRateSetting(mode: 'manual', default: null);

    expect($setting->badges())->toBeEmpty();
});
