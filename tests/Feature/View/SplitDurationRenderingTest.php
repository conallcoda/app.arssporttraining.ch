<?php

use App\Data\Exercise\Settings\DurationSetting;

it('formats split durations in planned and actual grid display cells', function () {
    $html = view('components.training.partials.planned-actual-value', [
        'plannedValue' => '10:00_10:00',
        'actualValue' => '10:30_10:15',
        'mode' => 'actual',
        'field' => 'duration',
    ])->render();

    expect($html)->toContain('10:00L_10:00R')
        ->and($html)->toContain('10:30L_10:15R');
});

it('keeps split reps formatted in planned and actual grid display cells', function () {
    $html = view('components.training.partials.planned-actual-value', [
        'plannedValue' => '6_6',
        'actualValue' => '7_7',
        'mode' => 'actual',
        'field' => 'reps',
    ])->render();

    expect($html)->toContain('6L_6R')
        ->and($html)->toContain('7L_7R');
});

it('renders split duration planner inputs as text fields with validation attributes', function () {
    $meta = DurationSetting::inputMeta(['unit' => 'mm:ss']);

    $this->blade('<x-training.exercise-grid-input :meta="$meta" value="10:00_10:00" />', [
        'meta' => $meta,
    ])
        ->assertSee('type="text"', false)
        ->assertSee('maxlength="15"', false)
        ->assertSee('pattern="(?:\d+|\d{1,3}:[0-5]\d)(?:_(?:\d+|\d{1,3}:[0-5]\d))?"', false);
});
