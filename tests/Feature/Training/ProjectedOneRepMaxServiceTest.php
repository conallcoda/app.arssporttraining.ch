<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Training\Blocks\BlockConfig;
use App\Exceptions\DuplicateManualMetricSubmission;
use App\Models\Athlete\MetricSubmission;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\ProjectedOneRepMaxService;
use App\Training\Reference\OneRepMaxConversion;

function createGroupWithAthletes(int $count = 1): array
{
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group', 'owner_id' => $coach->id]);
    $athletes = [];
    for ($i = 0; $i < $count; $i++) {
        $athlete = User::factory()->athlete()->create();
        $group->members()->attach($athlete->id, ['sort' => $i]);
        $athletes[] = $athlete;
    }

    return [$group, $athletes, $coach];
}

function createManual1rm(User $athlete, float $weight, int $reps = 1, string $date = '2026-01-01'): MetricSubmission
{
    return (new MetricSubmissionData(
        user_id: $athlete->id,
        recorded_at: $date,
        data: new OneRepMaxMetric(measuredReps: $reps, measuredWeight: $weight),
    ))->persist();
}

function createStrengthBlock(UserGroup $group, int $goal = 10, bool $autoRecord = true, ?int $userId = null, ?int $parentId = null): TrainingProgramBlock
{
    return TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $userId,
        'parent_id' => $parentId,
        'type' => 'category',
        'start' => '2026-02-01',
        'end' => '2026-03-01',
        'note' => 'Strength Block',
        'config' => new BlockConfig(goal: $goal, autoRecord1rm: $autoRecord),
        'active' => true,
    ]);
}

it('creates projected 1rm for group members with existing 1rm', function () {
    [$group, $athletes] = createGroupWithAthletes(2);
    createManual1rm($athletes[0], 100.0);
    createManual1rm($athletes[1], 80.0);

    $block = createStrengthBlock($group);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    $projections = MetricSubmission::projected()->get();
    expect($projections)->toHaveCount(2);

    $proj1 = $projections->firstWhere('user_id', $athletes[0]->id);
    expect($proj1->recorded_at->format('Y-m-d'))->toBe('2026-03-01');
    expect($proj1->owner_type)->toBe(TrainingProgramBlock::class);
    expect($proj1->owner_id)->toBe($block->id);

    $expected1 = OneRepMaxConversion::targetOneRepMax(100.0, 10);
    $stored1 = (float) $proj1->getFieldValue('estimated1RM');
    expect($stored1)->toBe($expected1);
});

it('skips athletes without prior 1rm', function () {
    [$group, $athletes] = createGroupWithAthletes(2);
    createManual1rm($athletes[0], 100.0);

    $block = createStrengthBlock($group);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    $projections = MetricSubmission::projected()->get();
    expect($projections)->toHaveCount(1);
    expect($projections->first()->user_id)->toBe($athletes[0]->id);
});

it('recalculates projection when goal changes', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $block = createStrengthBlock($group, goal: 10);
    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    $expected10 = OneRepMaxConversion::targetOneRepMax(100.0, 10);
    $proj = MetricSubmission::projected()->first();
    expect((float) $proj->getFieldValue('estimated1RM'))->toBe($expected10);

    $block->update(['config' => new BlockConfig(goal: 20, autoRecord1rm: true)]);
    $service->syncForBlock($block->fresh());

    $expected20 = OneRepMaxConversion::targetOneRepMax(100.0, 20);
    $proj = MetricSubmission::projected()->first();
    expect((float) $proj->getFieldValue('estimated1RM'))->toBe($expected20);
    expect(MetricSubmission::projected()->count())->toBe(1);
});

it('removes projections when autoRecord1rm is disabled', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $block = createStrengthBlock($group);
    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    expect(MetricSubmission::projected()->count())->toBe(1);

    $block->update(['config' => new BlockConfig(goal: 10, autoRecord1rm: false)]);
    $service->syncForBlock($block->fresh());

    expect(MetricSubmission::projected()->count())->toBe(0);
});

it('removes projections when block is deleted', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $block = createStrengthBlock($group);
    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    expect(MetricSubmission::projected()->count())->toBe(1);

    $service->removeForBlock($block);

    expect(MetricSubmission::projected()->count())->toBe(0);
});

it('uses athlete override goal instead of parent goal', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $parentBlock = createStrengthBlock($group, goal: 10);
    $childBlock = createStrengthBlock($group, goal: 25, userId: $athletes[0]->id, parentId: $parentBlock->id);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($childBlock);
    $service->syncForBlock($parentBlock->fresh());

    $projections = MetricSubmission::projected()->get();
    expect($projections)->toHaveCount(1);

    $proj = $projections->first();
    expect($proj->owner_id)->toBe($childBlock->id);

    $expected = OneRepMaxConversion::targetOneRepMax(100.0, 25);
    expect((float) $proj->getFieldValue('estimated1RM'))->toBe($expected);
});

it('re-generates parent projection when override is deactivated', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $parentBlock = createStrengthBlock($group, goal: 10);
    $childBlock = createStrengthBlock($group, goal: 25, userId: $athletes[0]->id, parentId: $parentBlock->id);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($childBlock);
    $service->syncForBlock($parentBlock->fresh());

    $service->removeForBlock($childBlock);
    $childBlock->update(['active' => false]);
    $service->syncForBlock($parentBlock->fresh());

    $proj = MetricSubmission::projected()->first();
    expect($proj->owner_id)->toBe($parentBlock->id);

    $expected = OneRepMaxConversion::targetOneRepMax(100.0, 10);
    expect((float) $proj->getFieldValue('estimated1RM'))->toBe($expected);
});

it('stores goalPercent as metric value', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $block = createStrengthBlock($group, goal: 15);
    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    $proj = MetricSubmission::projected()->first();
    expect($proj->getFieldValue('goalPercent'))->toBe('15');
});

it('creates projection when athlete adds 1rm later', function () {
    [$group, $athletes] = createGroupWithAthletes(1);

    $block = createStrengthBlock($group, goal: 10);
    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    expect(MetricSubmission::projected()->count())->toBe(0);

    createManual1rm($athletes[0], 100.0);
    $service->syncForAthleteBlocks($athletes[0]->id);

    expect(MetricSubmission::projected()->count())->toBe(1);

    $expected = OneRepMaxConversion::targetOneRepMax(100.0, 10);
    $proj = MetricSubmission::projected()->first();
    expect((float) $proj->getFieldValue('estimated1RM'))->toBe($expected);
});

it('recalculates projection when athlete updates manual 1rm', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $block = createStrengthBlock($group, goal: 10);
    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    $expected100 = OneRepMaxConversion::targetOneRepMax(100.0, 10);
    expect((float) MetricSubmission::projected()->first()->getFieldValue('estimated1RM'))->toBe($expected100);

    createManual1rm($athletes[0], 120.0, date: '2026-01-15');
    $service->syncForAthleteBlocks($athletes[0]->id);

    $expected120 = OneRepMaxConversion::targetOneRepMax(120.0, 10);
    expect((float) MetricSubmission::projected()->first()->getFieldValue('estimated1RM'))->toBe($expected120);
    expect(MetricSubmission::projected()->count())->toBe(1);
});

it('does not create projection when block has no end date', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    createManual1rm($athletes[0], 100.0);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'category',
        'start' => '2026-02-01',
        'end' => null,
        'note' => 'Open-ended block',
        'config' => new BlockConfig(goal: 10, autoRecord1rm: true),
        'active' => true,
    ]);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($block);

    expect(MetricSubmission::projected()->count())->toBe(0);
});

it('uses a same-day manual override at the boundary between projected blocks', function () {
    [$group, $athletes, $coach] = createGroupWithAthletes(1);
    $athlete = $athletes[0];

    createManual1rm($athlete, 19, reps: 8, date: '2026-06-17');

    $blockOne = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'category',
        'start' => '2026-06-22',
        'end' => '2026-08-01',
        'note' => 'Strength Block 1',
        'config' => new BlockConfig(goal: 15, autoRecord1rm: true),
        'active' => true,
    ]);

    $blockTwo = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'category',
        'start' => '2026-08-01',
        'end' => '2026-09-05',
        'note' => 'Strength Block 2',
        'config' => new BlockConfig(goal: 10, autoRecord1rm: true),
        'active' => true,
    ]);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForAthleteBlocks($athlete->id);

    $blockOneProjection = MetricSubmission::forBlock($blockOne->id)
        ->forAthlete($athlete->id)
        ->with('values')
        ->firstOrFail();

    expect((float) $blockOneProjection->getFieldValue('estimated1RM'))->toBe(27.4)
        ->and((float) MetricSubmission::forBlock($blockTwo->id)->firstOrFail()->getFieldValue('estimated1RM'))->toBe(30.1);

    $manualOverride = MetricSubmissionData::fromModel($blockOneProjection);
    $manualOverride->recorded_by = $coach->id;
    $manualOverride->data->measuredReps = 10;
    $manualOverride->data->measuredWeight = 24;
    $savedManual = $manualOverride->persist();

    $service->syncForAthleteBlocks($athlete->id);

    $sameDaySubmissions = MetricSubmission::query()
        ->forAthlete($athlete->id)
        ->forMetric(MetricEnum::OneRepMax)
        ->whereDate('recorded_at', '2026-08-01')
        ->get();

    expect($savedManual->id)->not->toBe($blockOneProjection->id)
        ->and($sameDaySubmissions)->toHaveCount(2)
        ->and($sameDaySubmissions->filter(fn (MetricSubmission $submission) => $submission->owner_type === TrainingProgramBlock::class))->toHaveCount(1)
        ->and($sameDaySubmissions->filter(fn (MetricSubmission $submission) => $submission->owner_type === User::class))->toHaveCount(1)
        ->and((float) $savedManual->getFieldValue('estimated1RM'))->toBe(32.4)
        ->and($savedManual->getFieldValue('goalPercent'))->toBeNull()
        ->and((float) MetricSubmission::forBlock($blockOne->id)->firstOrFail()->getFieldValue('estimated1RM'))->toBe(27.4)
        ->and((float) MetricSubmission::forBlock($blockTwo->id)->firstOrFail()->getFieldValue('estimated1RM'))->toBe(35.6);

    $secondEdit = MetricSubmissionData::fromModel($blockOneProjection->fresh('values'));
    $secondEdit->recorded_by = $coach->id;
    $secondEdit->data->measuredReps = 10;
    $secondEdit->data->measuredWeight = 24;

    expect(fn () => $secondEdit->persist())
        ->toThrow(DuplicateManualMetricSubmission::class);

    expect(MetricSubmission::query()
        ->forAthlete($athlete->id)
        ->forMetric(MetricEnum::OneRepMax)
        ->whereDate('recorded_at', '2026-08-01')
        ->count())->toBe(2);
});

it('keeps only one automatic 1RM per athlete and date', function () {
    [$group, $athletes] = createGroupWithAthletes(1);
    $athlete = $athletes[0];
    createManual1rm($athlete, 100, date: '2026-01-01');

    $firstBlock = createStrengthBlock($group, goal: 10);
    $secondBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'type' => 'category',
        'start' => '2026-02-15',
        'end' => '2026-03-01',
        'note' => 'Second projection on the same day',
        'config' => new BlockConfig(goal: 20, autoRecord1rm: true),
        'active' => true,
    ]);

    $service = app(ProjectedOneRepMaxService::class);
    $service->syncForBlock($firstBlock);
    $service->syncForBlock($secondBlock);

    $automatic = MetricSubmission::query()
        ->projected()
        ->forAthlete($athlete->id)
        ->whereDate('recorded_at', '2026-03-01')
        ->get();

    expect($automatic)->toHaveCount(1)
        ->and($automatic->first()->owner_id)->toBe($secondBlock->id);
});
