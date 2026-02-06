<?php

use App\Data\Training\Config\TrainingPlanConfig;
use App\Training\Services\TrainingPlanConfigResolver;

function sampleResolver(?int $userId = null): TrainingPlanConfigResolver
{
    $json = json_decode(file_get_contents(base_path('a.json')), true);

    return new TrainingPlanConfigResolver(
        TrainingPlanConfig::from($json),
        $userId,
    );
}

it('resolves default schedule weeks when no user is set', function () {
    $resolver = sampleResolver();
    $weeks = $resolver->resolveScheduleWeeks();

    expect($weeks)->toHaveCount(5);
    expect($weeks[0]['id'])->toBe('default_0');
    expect($weeks[0]['linkedTo'])->toBeNull();
    expect($weeks[1]['linkedTo'])->toBe('default_0');
});

it('returns default weeks when user has no overrides', function () {
    $resolver = sampleResolver(999);
    $weeks = $resolver->resolveScheduleWeeks();

    expect($weeks)->toHaveCount(5);
});

it('merges slot overrides correctly', function () {
    $resolver = sampleResolver();

    $base = [
        ['day' => 0, 'slot' => 0, 'programId' => 1],
        ['day' => 2, 'slot' => 1, 'programId' => 2],
    ];

    $overrides = [
        ['day' => 0, 'slot' => 0, 'programId' => 3],
    ];

    $result = $resolver->mergeSlotOverrides($base, $overrides);

    expect($result)->toHaveCount(2);
    $slot = collect($result)->firstWhere('day', 0);
    expect($slot['programId'])->toBe(3);
});

it('removes slot when override has null programId', function () {
    $resolver = sampleResolver();

    $base = [
        ['day' => 0, 'slot' => 0, 'programId' => 1],
    ];

    $overrides = [
        ['day' => 0, 'slot' => 0, 'programId' => null],
    ];

    $result = $resolver->mergeSlotOverrides($base, $overrides);

    expect($result)->toBeEmpty();
});

it('resolves slots for week following linked chain', function () {
    $resolver = sampleResolver();
    $weeks = $resolver->resolveScheduleWeeks();

    $linkedWeek = collect($weeks)->firstWhere('id', 'default_1');
    $slots = $resolver->getResolvedSlotsForWeek($linkedWeek, $weeks);

    expect($slots)->toHaveCount(3);
});

it('resolves slots to dense 7x2 grid', function () {
    $resolver = sampleResolver();
    $weeks = $resolver->resolveScheduleWeeks();

    $dense = $resolver->getResolvedSlotsDense($weeks[0], $weeks);

    expect($dense)->toHaveCount(7);
    expect($dense[0][0]['programId'])->toBe(1);
    expect($dense[1][0]['programId'])->toBeNull();
});

it('extracts program IDs from schedule', function () {
    $resolver = sampleResolver();

    $programIds = $resolver->programIdsFromSchedule();

    expect($programIds)->toContain(1);
    expect($programIds)->toContain(2);
});

it('counts sessions per week for a program', function () {
    $resolver = sampleResolver();

    $sessions = $resolver->sessionsPerWeekForProgram(1);

    expect($sessions)->toBe(2);
});

it('returns minimum of 1 session for unknown program', function () {
    $resolver = sampleResolver();

    $sessions = $resolver->sessionsPerWeekForProgram(999);

    expect($sessions)->toBe(1);
});

it('resolves exercise config for default user', function () {
    $resolver = sampleResolver();
    $pivotConfig = ['startingReps' => 10, 'sets' => 4, 'tut' => '3010', 'rest' => 30, 'oneRepMaxModifier' => 100];

    $config = $resolver->getExerciseConfig(1, $pivotConfig, 7);

    expect($config['target'])->toBe(7);
    expect($config['hasTargetOverride'])->toBeFalse();
});

it('resolves exercise config cascading user over default for user 5', function () {
    $resolver = sampleResolver(5);
    $pivotConfig = ['startingReps' => 10, 'sets' => 4, 'tut' => '3010', 'rest' => 30, 'oneRepMaxModifier' => 100];

    $config = $resolver->getExerciseConfig(1, $pivotConfig, 7);

    expect($config['target'])->toBe(71);
    expect($config['startingReps'])->toBe(12);
    expect($config['sets'])->toBe(4);
    expect($config['tut'])->toBe('3010');
    expect($config['rest'])->toBe(30);
    expect($config['hasTargetOverride'])->toBeTrue();
});

it('falls back to system defaults when no overrides exist', function () {
    $resolver = sampleResolver();
    $pivotConfig = ['startingReps' => 10, 'sets' => 4, 'tut' => '3010', 'rest' => 30, 'oneRepMaxModifier' => 100];

    $config = $resolver->getExerciseConfig(999, $pivotConfig, 7);

    expect($config['target'])->toBe(7);
    expect($config['startingReps'])->toBe(10);
    expect($config['sets'])->toBe(4);
    expect($config['tut'])->toBe('3010');
    expect($config['rest'])->toBe(30);
    expect($config['hasTargetOverride'])->toBeFalse();
});

it('returns export exercise config without override flags', function () {
    $resolver = sampleResolver(5);
    $pivotConfig = ['startingReps' => 10, 'sets' => 4, 'tut' => '3010', 'rest' => 30, 'oneRepMaxModifier' => 100];

    $config = $resolver->getExerciseConfigForExport(1, $pivotConfig, 7);

    expect($config)->not->toHaveKey('hasTargetOverride');
    expect($config['target'])->toBe(71);
});

it('returns default cell overrides when no user', function () {
    $resolver = sampleResolver();

    $overrides = $resolver->getCellOverrides(1);

    expect($overrides)->toHaveCount(2);
    expect($overrides[0]['data']['reps'])->toBe(33);
});

it('merges default and user cell overrides', function () {
    $resolver = sampleResolver(5);

    $overrides = $resolver->getCellOverrides(1);

    expect($overrides)->toHaveCount(2);

    $firstOverride = $overrides[0];
    expect($firstOverride['data'])->toHaveKey('reps');
    expect($firstOverride['data'])->toHaveKey('weight');
});

it('returns empty cell overrides for non-existent exercise', function () {
    $resolver = sampleResolver();

    expect($resolver->getCellOverrides(999))->toBeEmpty();
});

it('returns default week overrides when no user', function () {
    $resolver = sampleResolver();

    $overrides = $resolver->getWeekOverrides(1);

    expect($overrides)->not->toBeEmpty();
    expect($overrides[0]['week'])->toBe(0);
    expect($overrides[0]['data']['tut'])->toBe('222');
});

it('merges default and user week overrides', function () {
    $resolver = sampleResolver(5);

    $overrides = $resolver->getWeekOverrides(1);

    $week0 = collect($overrides)->firstWhere('week', 0);
    expect($week0['data']['tut'])->toBe('111');
});

it('detects user has no schedule overrides for user 5', function () {
    $resolver = sampleResolver();

    expect($resolver->hasUserSchedule(5))->toBeFalse();
    expect($resolver->hasUserSchedule(999))->toBeFalse();
});

it('counts user schedule changes', function () {
    $resolver = sampleResolver();

    expect($resolver->countUserScheduleChanges(5))->toBe(0);
    expect($resolver->countUserScheduleChanges(999))->toBe(0);
});

it('creates a new resolver for a different user via forUser', function () {
    $resolver = sampleResolver();
    $userResolver = $resolver->forUser(5);

    $defaultConfig = $resolver->getExerciseConfig(1, [], 7);
    $userConfig = $userResolver->getExerciseConfig(1, [], 7);

    expect($userConfig['target'])->toBe(71);
    expect($defaultConfig['target'])->toBe(7);
});
