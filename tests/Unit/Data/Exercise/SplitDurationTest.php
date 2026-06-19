<?php

use App\Data\Exercise\Settings\DurationSetting;
use App\Data\Exercise\SplitDuration;

it('parses scalar seconds', function () {
    $duration = SplitDuration::parse('600', 'seconds');

    expect($duration?->parts)->toBe([600])
        ->and((string) $duration)->toBe('600')
        ->and($duration?->display())->toBe('600');
});

it('parses split seconds', function () {
    $duration = SplitDuration::parse('600_600', 'seconds');

    expect($duration?->parts)->toBe([600, 600])
        ->and((string) $duration)->toBe('600_600')
        ->and($duration?->display())->toBe('600L_600R');
});

it('normalizes mm:ss split values to seconds for storage and display', function () {
    $duration = SplitDuration::parse('10:00_10:30', 'mm:ss');

    expect($duration?->parts)->toBe([600, 630])
        ->and((string) $duration)->toBe('600_630')
        ->and($duration?->display())->toBe('10:00L_10:30R');
});

it('keeps minute-based storage in minutes while canonical parts stay seconds', function () {
    $duration = SplitDuration::parse('10_12', 'minutes');

    expect($duration?->parts)->toBe([600, 720])
        ->and((string) $duration)->toBe('10_12')
        ->and($duration?->display())->toBe('10L_12R');
});

it('rejects invalid time parts', function () {
    expect(SplitDuration::parse('10:99_10:00', 'mm:ss'))->toBeNull()
        ->and(SplitDuration::parse('10:00_10:00_10:00', 'mm:ss'))->toBeNull();
});

it('formats duration values through the setting', function () {
    expect(DurationSetting::formatAthleteValue('600_600', config: ['unit' => 'mm:ss']))->toBe('10:00L_10:00R')
        ->and(DurationSetting::formatAthleteValue('600_600', config: ['unit' => 'seconds']))->toBe('600L_600R')
        ->and(DurationSetting::formatAthleteValue('10_12', config: ['unit' => 'minutes']))->toBe('10L_12R');
});
