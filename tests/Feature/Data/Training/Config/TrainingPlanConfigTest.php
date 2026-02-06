<?php

use App\Data\Training\Config\DefaultTrainingPlanConfig;
use App\Data\Training\Config\Exercise\AthleteExerciseConfig;
use App\Data\Training\Config\Exercise\AthleteStrengthConfig;
use App\Data\Training\Config\Exercise\DefaultExerciseConfig;
use App\Data\Training\Config\Exercise\DefaultStrengthConfig;
use App\Data\Training\Config\Exercise\ExerciseOverride;
use App\Data\Training\Config\Exercise\ExerciseOverrideConfig;
use App\Data\Training\Config\Schedule\DefaultScheduleConfig;
use App\Data\Training\Config\Schedule\ScheduleWeek;
use App\Data\Training\Config\Schedule\ScheduleWeekSlot;
use App\Data\Training\Config\TrainingPlanConfig;
use App\Data\Training\Config\UserTrainingPlanConfig;
use Spatie\LaravelData\Optional;

function sampleConfig(): array
{
    return json_decode(file_get_contents(base_path('a.json')), true);
}

it('deserializes from the full JSON config', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config)->toBeInstanceOf(TrainingPlanConfig::class);
    expect($config->default)->toBeInstanceOf(DefaultTrainingPlanConfig::class);
    expect($config->users)->toBeArray();
});

it('deserializes default exercise config with strength values', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $exerciseConfig = $config->default->exerciseConfig;

    expect($exerciseConfig)->toBeInstanceOf(DefaultExerciseConfig::class);
    expect($exerciseConfig->strength)->toBeInstanceOf(DefaultStrengthConfig::class);
    expect($exerciseConfig->strength->measuredReps)->toBe(8);
    expect($exerciseConfig->strength->measuredWeight)->toBe(52.0);
    expect($exerciseConfig->strength->targetGoal)->toBe(7);
});

it('deserializes default schedule with start date and weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $schedule = $config->default->schedule;

    expect($schedule)->toBeInstanceOf(DefaultScheduleConfig::class);
    expect($schedule->startDate)->toBe('2026-02-02');
    expect($schedule->weeks)->toHaveCount(5);
});

it('deserializes schedule weeks as typed ScheduleWeek objects', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $firstWeek = $config->default->schedule->weeks[0];

    expect($firstWeek)->toBeInstanceOf(ScheduleWeek::class);
    expect($firstWeek->id)->toBe('default_0');
    expect($firstWeek->linkedTo)->toBeNull();
    expect($firstWeek->sort)->toBe(0);
    expect($firstWeek->slots)->toHaveCount(3);
});

it('deserializes linked weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $secondWeek = $config->default->schedule->weeks[1];

    expect($secondWeek)->toBeInstanceOf(ScheduleWeek::class);
    expect($secondWeek->id)->toBe('default_1');
    expect($secondWeek->linkedTo)->toBe('default_0');
    expect($secondWeek->slots)->toBeEmpty();
    expect($secondWeek->sort)->toBe(1);
});

it('deserializes schedule week slots as typed objects with isLinked flag', function () {
    $config = TrainingPlanConfig::from(sampleConfig());
    $slots = $config->default->schedule->weeks[0]->slots;

    $regularSlot = collect($slots)->firstWhere('day', 0);
    expect($regularSlot)->toBeInstanceOf(ScheduleWeekSlot::class);
    expect($regularSlot->programId)->toBe(1);
    expect($regularSlot->isLinked)->toBeInstanceOf(Optional::class);

    $linkedSlot = collect($slots)->firstWhere('day', 4);
    expect($linkedSlot)->toBeInstanceOf(ScheduleWeekSlot::class);
    expect($linkedSlot->programId)->toBe(1);
    expect($linkedSlot->isLinked)->toBeTrue();
});

it('deserializes default exercise overrides as typed objects', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $exercises = $config->default->exercises;

    expect($exercises)->toHaveCount(1);
    expect($exercises[0])->toBeInstanceOf(ExerciseOverride::class);
    expect($exercises[0]->id)->toBe(1);
    expect($exercises[0]->config)->toBeInstanceOf(ExerciseOverrideConfig::class);
    expect($exercises[0]->config->target)->toBe(7);
    expect($exercises[0]->config->startingReps)->toBe(12);
    expect($exercises[0]->config->sets)->toBe(4);
    expect($exercises[0]->config->tut)->toBe('2000');
    expect($exercises[0]->config->rest)->toBe(30);
});

it('deserializes default cell overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $cells = $config->default->cells;

    expect($cells)->toHaveKey('1');
    expect($cells['1'])->toHaveCount(2);

    $firstCell = $cells['1'][0];
    expect($firstCell['week'])->toBe(0);
    expect($firstCell['session'])->toBe(0);
    expect($firstCell['set'])->toBe(2);
    expect($firstCell['data']['reps'])->toBe(5);
});

it('returns null for non-existent user', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->forUser(999))->toBeNull();
});

it('returns typed UserTrainingPlanConfig via forUser accessor', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);

    expect($userConfig)->toBeInstanceOf(UserTrainingPlanConfig::class);
});

it('deserializes user schedule with week overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);
    $schedule = $userConfig->schedule;

    expect($schedule)->toBeInstanceOf(DefaultScheduleConfig::class);
    expect($schedule->weeks)->toHaveCount(3);

    $defaultWeekOverride = collect($schedule->weeks)->firstWhere('id', 'default_0');
    expect($defaultWeekOverride)->not->toBeNull();
    expect($defaultWeekOverride->slots)->toHaveCount(2);
});

it('deserializes user-added weeks with sort', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);
    $userAddedWeek = collect($userConfig->schedule->weeks)->firstWhere('id', 'user_5');

    expect($userAddedWeek)->not->toBeNull();
    expect($userAddedWeek)->toBeInstanceOf(ScheduleWeek::class);
    expect($userAddedWeek->sort)->toBe(5);
    expect($userAddedWeek->slots)->toHaveCount(3);
});

it('deserializes user schedule slots with moved property', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);
    $defaultWeek = collect($userConfig->schedule->weeks)->firstWhere('id', 'default_0');
    $movedSlot = collect($defaultWeek->slots)->firstWhere('day', 1);

    expect($movedSlot)->toBeInstanceOf(ScheduleWeekSlot::class);
    expect($movedSlot->moved)->toBe([2, 1]);
    expect($movedSlot->programId)->toBeNull();
});

it('deserializes user exercise config with athlete strength values', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);

    expect($userConfig->exerciseConfig)->toBeInstanceOf(AthleteExerciseConfig::class);
    expect($userConfig->exerciseConfig->strength)->toBeInstanceOf(AthleteStrengthConfig::class);
    expect($userConfig->exerciseConfig->strength->measuredReps)->toBe(22);
    expect($userConfig->exerciseConfig->strength->measuredWeight)->toBe(33.0);
    expect($userConfig->exerciseConfig->strength->targetGoal)->toBe(5);
});

it('deserializes user exercise overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);

    expect($userConfig->exercises)->toHaveCount(1);
    expect($userConfig->exercises[0])->toBeInstanceOf(ExerciseOverride::class);
    expect($userConfig->exercises[0]->id)->toBe(1);
    expect($userConfig->exercises[0]->config->target)->toBe(5);
    expect($userConfig->exercises[0]->config->startingReps)->toBe(8);
    expect($userConfig->exercises[0]->config->sets)->toBe(3);
    expect($userConfig->exercises[0]->config->tut)->toBe('3000');
    expect($userConfig->exercises[0]->config->rest)->toBe(25);
});

it('deserializes user cell overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);

    expect($userConfig->cells)->toHaveKey('1');
    expect($userConfig->cells['1'])->toHaveCount(2);

    $firstCell = $userConfig->cells['1'][0];
    expect($firstCell['week'])->toBe(0);
    expect($firstCell['session'])->toBe(0);
    expect($firstCell['set'])->toBe(2);
    expect($firstCell['data']['weight'])->toBe(9);
});

it('round-trips to array matching the original structure', function () {
    $json = sampleConfig();
    $config = TrainingPlanConfig::from($json);
    $output = $config->toArray();

    expect($output['default']['exerciseConfig']['strength']['measuredReps'])->toBe(8);
    expect($output['default']['exerciseConfig']['strength']['measuredWeight'])->toBe(52.0);
    expect($output['default']['schedule']['startDate'])->toBe('2026-02-02');
    expect($output['default']['schedule']['weeks'])->toHaveCount(5);
    expect($output['default']['exercises'])->toHaveCount(1);
    expect($output['default']['cells'])->toHaveKey('1');
});

it('deserializes default week overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $weeks = $config->default->weeks;

    expect($weeks)->toHaveKey('1');
    expect($weeks['1'])->toHaveKey('w0');
    expect($weeks['1']['w0']['tut'])->toBe('4010');
    expect($weeks['1']['w0']['rest'])->toBe(60);
    expect($weeks['1']['w2']['tut'])->toBe('3010');
});

it('deserializes user week overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $userConfig = $config->forUser(7);

    expect($userConfig->weeks)->toHaveKey('1');
    expect($userConfig->weeks['1']['w0']['rest'])->toBe(90);
});

it('round-trips weeks in round-trip serialization', function () {
    $json = sampleConfig();
    $config = TrainingPlanConfig::from($json);
    $output = $config->toArray();

    expect($output['default']['weeks'])->toHaveKey('1');
    expect($output['default']['weeks']['1']['w0']['tut'])->toBe('4010');
});

it('can be created with minimal data', function () {
    $config = TrainingPlanConfig::from([
        'default' => [],
    ]);

    expect($config)->toBeInstanceOf(TrainingPlanConfig::class);
    expect($config->default->exerciseConfig)->toBeInstanceOf(Optional::class);
    expect($config->default->schedule)->toBeInstanceOf(Optional::class);
    expect($config->default->exercises)->toBeEmpty();
    expect($config->default->cells)->toBeEmpty();
    expect($config->default->weeks)->toBeEmpty();
    expect($config->users)->toBeEmpty();
});

it('can be created via initialize factory', function () {
    $config = TrainingPlanConfig::initialize();

    expect($config)->toBeInstanceOf(TrainingPlanConfig::class);
    expect($config->default->exercises)->toBeEmpty();
    expect($config->defaultScheduleWeeks())->toBeEmpty();
    expect($config->defaultExerciseOverrides())->toBeEmpty();
});

it('provides typed accessor for default schedule weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $weeks = $config->defaultScheduleWeeks();

    expect($weeks)->toHaveCount(5);
});

it('provides typed accessor for user schedule weeks', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->userScheduleWeeks(7))->toHaveCount(3);
    expect($config->userScheduleWeeks(999))->toBeEmpty();
});

it('provides typed accessor for default exercise overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->defaultExerciseOverrides())->toHaveCount(1);
});

it('provides typed accessor for user exercise overrides', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->userExerciseOverrides(7))->toHaveCount(1);
    expect($config->userExerciseOverrides(999))->toBeEmpty();
});

it('provides typed accessor for cell overrides by exercise', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->defaultCellOverrides(1))->toHaveCount(2);
    expect($config->defaultCellOverrides(999))->toBeEmpty();
    expect($config->userCellOverrides(7, 1))->toHaveCount(2);
    expect($config->userCellOverrides(999, 1))->toBeEmpty();
});

it('provides typed accessor for week overrides by exercise', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    expect($config->defaultWeekOverrides(1))->toHaveKey('w0');
    expect($config->defaultWeekOverrides(999))->toBeEmpty();
    expect($config->userWeekOverrides(7, 1))->toHaveKey('w0');
    expect($config->userWeekOverrides(999, 1))->toBeEmpty();
});

it('provides typed accessor for default exercise config', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $exerciseConfig = $config->defaultExerciseConfig();

    expect($exerciseConfig['measuredReps'])->toBe(8);
    expect($exerciseConfig['measuredWeight'])->toBe(52.0);
    expect($exerciseConfig['targetGoal'])->toBe(7);
});

it('provides typed accessor for user exercise config', function () {
    $config = TrainingPlanConfig::from(sampleConfig());

    $exerciseConfig = $config->userExerciseConfig(7);

    expect($exerciseConfig['measuredReps'])->toBe(22);
    expect($exerciseConfig['measuredWeight'])->toBe(33.0);
    expect($exerciseConfig['targetGoal'])->toBe(5);

    expect($config->userExerciseConfig(999))->toBeEmpty();
});
