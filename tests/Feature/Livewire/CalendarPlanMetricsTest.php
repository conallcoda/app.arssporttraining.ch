<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarIndex;
use App\Livewire\Training\CalendarProgramsView;
use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\CalendarDateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSubmissionWithValues(array $submissionAttrs, array $values): MetricSubmission
{
    $submission = MetricSubmission::create($submissionAttrs);
    foreach ($values as $field => $value) {
        MetricValue::create([
            'submission_id' => $submission->id,
            'field' => $field,
            'value' => (string) $value,
        ]);
    }

    return $submission;
}

function mountCalendarPlan(array $props): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(CalendarIndex::class, array_merge([
        'view' => 'plan',
    ], $props));
}

// --- planMeasuredData ---

it('returns default measured data when no user is selected', function () {
    $group = UserGroup::create(['name' => 'Test Group']);

    $component = mountCalendarPlan(['group' => (string) $group->id]);

    $data = $component->instance()->planMeasuredData;
    expect($data)->toBe(['measuredReps' => 1, 'measuredWeight' => 50]);
});

it('returns null measured data when user is selected but no submission exists', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-01',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ]);

    $data = $component->instance()->planMeasuredData;
    expect($data)->toBe(['measuredReps' => null, 'measuredWeight' => null]);
});

it('returns measured data from 1RM submission before block start', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-05',
    ], [
        'measuredReps' => '3',
        'measuredWeight' => '85.5',
    ]);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ]);

    $data = $component->instance()->planMeasuredData;
    expect($data['measuredReps'])->toBe(3);
    expect($data['measuredWeight'])->toBe(85.5);
});

it('ignores 1RM submissions after block start date', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-15',
    ], [
        'measuredReps' => '5',
        'measuredWeight' => '100',
    ]);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ]);

    $data = $component->instance()->planMeasuredData;
    expect($data)->toBe(['measuredReps' => null, 'measuredWeight' => null]);
});

it('returns null measured data when planBlock is ungrouped and user selected', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => 'ungrouped',
    ]);

    $data = $component->instance()->planMeasuredData;
    expect($data)->toBe(['measuredReps' => null, 'measuredWeight' => null]);
});

// --- planHeartRateData ---

it('returns null heart rate data when no user is selected', function () {
    $group = UserGroup::create(['name' => 'Test Group']);

    $component = mountCalendarPlan(['group' => (string) $group->id]);

    $data = $component->instance()->planHeartRateData;
    expect($data)->toBe(['maxHR' => null, 'iatPercent' => null]);
});

it('returns heart rate data from manual submission before block start', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-08',
    ], [
        'heartRate' => '185',
        'anaerobicThreshold' => '88',
    ]);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ]);

    $data = $component->instance()->planHeartRateData;
    expect($data['maxHR'])->toBe(185);
    expect($data['iatPercent'])->toBe(88);
});

it('ignores projected heart rate submissions', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-05',
        'owner_type' => TrainingProgramBlock::class,
        'owner_id' => $block->id,
    ], [
        'heartRate' => '190',
        'anaerobicThreshold' => '92',
    ]);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ]);

    $data = $component->instance()->planHeartRateData;
    expect($data)->toBe(['maxHR' => null, 'iatPercent' => null]);
});

// --- planGroupMemberMetrics ---

it('returns empty metrics when a specific user is selected', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
    ]);

    $data = $component->instance()->planGroupMemberMetrics;
    expect($data)->toBe(['oneRepMax' => [], 'heartRate' => []]);
});

it('returns metrics for all group members in group mode', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $athlete1 = User::factory()->athlete()->create(['forename' => 'Alice', 'surname' => 'A']);
    $athlete2 = User::factory()->athlete()->create(['forename' => 'Bob', 'surname' => 'B']);
    $coach = User::factory()->coach()->create();
    $group->members()->attach([$athlete1->id, $athlete2->id]);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    createSubmissionWithValues([
        'user_id' => $athlete1->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-05',
    ], [
        'measuredReps' => '1',
        'measuredWeight' => '100',
    ]);

    createSubmissionWithValues([
        'user_id' => $athlete2->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-08',
    ], [
        'heartRate' => '175',
        'anaerobicThreshold' => '85',
    ]);

    $component = mountCalendarPlan([
        'group' => (string) $group->id,
        'planBlock' => (string) $block->id,
    ]);

    $data = $component->instance()->planGroupMemberMetrics;

    expect($data['oneRepMax'])->toHaveCount(2);
    expect($data['heartRate'])->toHaveCount(2);

    $alice1rm = collect($data['oneRepMax'])->firstWhere('user_id', $athlete1->id);
    expect($alice1rm['label'])->toBe('100kg');

    $bob1rm = collect($data['oneRepMax'])->firstWhere('user_id', $athlete2->id);
    expect($bob1rm['label'])->toBeNull();

    $aliceHr = collect($data['heartRate'])->firstWhere('user_id', $athlete1->id);
    expect($aliceHr['label'])->toBeNull();

    $bobHr = collect($data['heartRate'])->firstWhere('user_id', $athlete2->id);
    expect($bobHr['label'])->toBe('175 HR - 85% IAT');
});

it('excludes soft deleted submissions from metric summary dates', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-28',
    ], [
        'heartRate' => '190',
        'anaerobicThreshold' => '90',
    ])->delete();

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::Readiness,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-30',
    ], [
        'restingHeartRate' => '48',
        'restingHeartRateBaseline' => '50',
        'hrv' => '65',
        'sleepQuality' => '4',
        'sleepDuration' => '4',
        'altitudeAdjustment' => '0',
        'condition' => '5',
        'mood' => '5',
        'motivation' => '5',
        'soreness' => '5',
        'energy' => '5',
    ]);

    $component = Livewire::test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(
            start: '2026-04-28',
            end: '2026-05-02',
            preset: CalendarDateService::PRESET_CUSTOM,
        ),
        'weekStartsOn' => Carbon::MONDAY,
        'weekEndsOn' => Carbon::SUNDAY,
    ]);

    $dates = $component->instance()->metricSummaryDates;

    expect($dates)->toHaveKey('2026-04-30');
    expect($dates)->not->toHaveKey('2026-04-28');
});

it('refreshes calendar metric caches immediately after submitting a metric', function () {
    Carbon::setTestNow('2026-05-02 12:00:00');

    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $component = Livewire::actingAs($coach)->test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(
            start: '2026-04-28',
            end: '2026-05-02',
            preset: CalendarDateService::PRESET_CUSTOM,
        ),
        'weekStartsOn' => Carbon::MONDAY,
        'weekEndsOn' => Carbon::SUNDAY,
    ]);

    expect($component->instance()->metricsRenderKey)->toBe(0)
        ->and($component->instance()->metricSummaryDates)->not->toHaveKey('2026-04-30')
        ->and($component->instance()->getMetricRowData(MetricEnum::OneRepMax->value))->toBe([])
        ->and($component->instance()->currentMetricValues[MetricEnum::OneRepMax->value]['isAvailable'])->toBeFalse();

    $component->call('onMetricFormSubmitted', [
        'metric' => MetricEnum::OneRepMax->value,
        'user_id' => $user->id,
        'recorded_at' => '2026-04-30',
        'data' => [
            'measuredReps' => 3,
            'measuredWeight' => 100,
        ],
    ]);

    $rowData = $component->instance()->getMetricRowData(MetricEnum::OneRepMax->value);
    $current = $component->instance()->currentMetricValues[MetricEnum::OneRepMax->value];

    expect($component->instance()->metricsRenderKey)->toBe(1)
        ->and($component->instance()->metricSummaryDates)->toHaveKey('2026-04-30')
        ->and($rowData)->toHaveKey('2026-04-30')
        ->and($rowData['2026-04-30']['label'])->not->toBeNull()
        ->and($current['isAvailable'])->toBeTrue()
        ->and($current['summary'])->toBe('100kg');

    Carbon::setTestNow();
});

it('refreshes calendar metric caches immediately after deleting a metric', function () {
    Carbon::setTestNow('2026-05-02 12:00:00');

    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $submission = createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-30',
    ], [
        'heartRate' => '190',
        'anaerobicThreshold' => '90',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(
            start: '2026-04-28',
            end: '2026-05-02',
            preset: CalendarDateService::PRESET_CUSTOM,
        ),
        'weekStartsOn' => Carbon::MONDAY,
        'weekEndsOn' => Carbon::SUNDAY,
    ]);

    expect($component->instance()->metricSummaryDates)->toHaveKey('2026-04-30')
        ->and($component->instance()->getMetricRowData(MetricEnum::HeartRate->value))->toHaveKey('2026-04-30')
        ->and($component->instance()->currentMetricValues[MetricEnum::HeartRate->value]['isAvailable'])->toBeTrue();

    $component->set('pendingMetricDeleteId', $submission->id)
        ->call('deleteMetricSubmission');

    expect($component->instance()->metricsRenderKey)->toBe(1)
        ->and($component->instance()->metricSummaryDates)->not->toHaveKey('2026-04-30')
        ->and($component->instance()->getMetricRowData(MetricEnum::HeartRate->value))->toBe([])
        ->and($component->instance()->currentMetricValues[MetricEnum::HeartRate->value]['isAvailable'])->toBeFalse();

    Carbon::setTestNow();
});

it('uses the latest usable readiness submission for the current metric badge', function () {
    Carbon::setTestNow('2026-05-02 12:00:00');

    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::Readiness,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-30',
    ], [
        'sleepMinutes' => '450',
        'sleepQuality' => '4',
        'altitudeMeters' => '1800',
        'condition' => '4',
        'mood' => '4',
        'motivation' => '4',
        'soreness' => '4',
        'energy' => '4',
        'restingHeartRate' => '48',
        'restingHeartRateBaseline' => '46',
        'readinessScore' => '4.0571428571429',
        'trafficLight' => 'ready',
        'trafficLightLabel' => 'Ready',
        'trafficLightColor' => 'green',
    ]);

    createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::Readiness,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-05-01',
    ], [
        'sleepMinutes' => '450',
        'sleepQuality' => '4',
        'altitudeMeters' => '1800',
        'condition' => '4',
        'mood' => '4',
        'motivation' => '4',
        'soreness' => '4',
        'energy' => '4',
        'restingHeartRateBaseline' => '46',
        'trafficLightLabel' => 'Ready',
        'trafficLightColor' => 'green',
    ]);

    $component = Livewire::actingAs($coach)->test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(
            start: '2026-04-28',
            end: '2026-05-02',
            preset: CalendarDateService::PRESET_CUSTOM,
        ),
        'weekStartsOn' => Carbon::MONDAY,
        'weekEndsOn' => Carbon::SUNDAY,
    ]);

    $current = $component->instance()->currentMetricValues[MetricEnum::Readiness->value];

    expect($current['isAvailable'])->toBeTrue()
        ->and($current['summary'])->toBe('4.1')
        ->and($current['recorded_at'])->toBe('30.04.2026');

    Carbon::setTestNow();
});

// --- openPlan1rmEdit ---

it('dispatches metric form with existing 1RM submission data', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    $submission = createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-05',
    ], [
        'measuredReps' => '3',
        'measuredWeight' => '80',
    ]);

    mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ])
        ->call('openPlan1rmEdit')
        ->assertDispatched('open-calendar-metric-form', function ($event, $params) use ($submission) {
            return $params['data']['id'] === $submission->id
                && $params['data']['metric'] === MetricEnum::OneRepMax->value
                && str_contains($params['title'], '1RM');
        });
});

it('dispatches metric form with defaults when no 1RM submission exists', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ])
        ->call('openPlan1rmEdit')
        ->assertDispatched('open-calendar-metric-form', function ($event, $params) use ($user) {
            return $params['data']['metric'] === MetricEnum::OneRepMax->value
                && $params['data']['user_id'] === $user->id
                && $params['data']['recorded_at'] === '2026-03-10';
        });
});

// --- openPlanHeartRateEdit ---

it('dispatches metric form with existing heart rate submission data', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    $submission = createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-08',
    ], [
        'heartRate' => '185',
        'anaerobicThreshold' => '88',
    ]);

    mountCalendarPlan([
        'group' => (string) $group->id,
        'user' => (string) $user->id,
        'planBlock' => (string) $block->id,
    ])
        ->call('openPlanHeartRateEdit')
        ->assertDispatched('open-calendar-metric-form', function ($event, $params) use ($submission) {
            return $params['data']['id'] === $submission->id
                && $params['data']['metric'] === MetricEnum::HeartRate->value
                && str_contains($params['title'], 'Heart Rate');
        });
});

it('dispatches metric form when opening the current metric badge', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    $submission = createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-05',
    ], [
        'measuredReps' => '3',
        'measuredWeight' => '80',
    ]);

    [$rangeStart, $rangeEnd] = app(CalendarDateService::class)->presetRange(CalendarDateService::PRESET_NEXT_3_MONTHS);

    Livewire::test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(
            start: $rangeStart->format('Y-m-d'),
            end: $rangeEnd->format('Y-m-d'),
            preset: CalendarDateService::PRESET_NEXT_3_MONTHS,
        ),
        'weekStartsOn' => Carbon::MONDAY,
        'weekEndsOn' => Carbon::SUNDAY,
    ])
        ->call('openCurrentMetric', MetricEnum::OneRepMax->value)
        ->assertDispatched('open-calendar-metric-form', function ($event, $params) use ($submission) {
            return $params['data']['id'] === $submission->id
                && $params['data']['metric'] === MetricEnum::OneRepMax->value
                && str_contains($params['title'], '1RM');
        });
});

// --- openPlanMetricEdit (formerly openPlanGroupMemberMetricEdit) ---

it('dispatches metric form for a specific group member', function () {
    $group = UserGroup::create(['name' => 'Test Group']);
    $user = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group->members()->attach($user);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'focus',
        'start' => '2026-03-10',
        'end' => '2026-03-31',
        'note' => '',
        'active' => true,
    ]);

    $submission = createSubmissionWithValues([
        'user_id' => $user->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-03-05',
    ], [
        'measuredReps' => '5',
        'measuredWeight' => '70',
    ]);

    mountCalendarPlan([
        'group' => (string) $group->id,
        'planBlock' => (string) $block->id,
    ])
        ->call('openPlanGroupMemberMetricEdit', $user->id, 'oneRepMax')
        ->assertDispatched('open-calendar-metric-form', function ($event, $params) use ($submission) {
            return $params['data']['id'] === $submission->id
                && $params['data']['metric'] === MetricEnum::OneRepMax->value;
        });
});
