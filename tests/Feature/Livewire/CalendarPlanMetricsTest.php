<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Livewire\Training\CalendarIndex;
use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
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
