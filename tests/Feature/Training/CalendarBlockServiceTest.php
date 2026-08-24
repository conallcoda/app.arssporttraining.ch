<?php

use App\Models\Tag;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\CalendarBlockService;
use Carbon\Carbon;

it('only compares category blocks belonging to the selected athlete in athlete mode', function () {
    $group = UserGroup::create(['name' => 'Athlete Scope']);
    $athlete = User::factory()->athlete()->create();
    $group->members()->attach($athlete);
    $category = Tag::factory()->withScope('training_category')->create();

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-08-01',
        'end' => '2026-08-31',
        'note' => 'Shared block',
        'active' => true,
    ]);

    $service = app(CalendarBlockService::class);

    expect($service->findCategoryOverlap(
        groupId: $group->id,
        categoryId: $category->id,
        start: Carbon::parse('2026-08-10'),
        end: Carbon::parse('2026-08-20'),
        userId: $athlete->id,
    ))->toBeNull();

    $athleteBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-08-12',
        'end' => '2026-08-14',
        'note' => 'Athlete block',
        'active' => true,
    ]);

    expect($service->findCategoryOverlap(
        groupId: $group->id,
        categoryId: $category->id,
        start: Carbon::parse('2026-08-10'),
        end: Carbon::parse('2026-08-20'),
        userId: $athlete->id,
    )?->id)->toBe($athleteBlock->id);
});

it('only syncs parent dates when the selected athlete is the groups sole athlete', function () {
    $group = UserGroup::create(['name' => 'Sync Scope']);
    $athlete = User::factory()->athlete()->create();
    $teammate = User::factory()->athlete()->create();
    $group->members()->attach($athlete);

    $service = app(CalendarBlockService::class);

    expect($service->shouldSyncSingleAthleteParentDates($group->id, $athlete->id))->toBeTrue();

    $group->members()->attach($teammate);

    expect($service->shouldSyncSingleAthleteParentDates($group->id, $athlete->id))->toBeFalse();
});

it('allows an edit that does not introduce a new athlete overlap', function () {
    $group = UserGroup::create(['name' => 'Existing Overlap']);
    $athlete = User::factory()->athlete()->create();
    $group->members()->attach($athlete);
    $category = Tag::factory()->withScope('training_category')->create();

    $parentBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-07-08',
        'end' => '2026-09-11',
        'note' => 'Shared block',
        'active' => true,
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-07-08',
        'end' => '2026-07-27',
        'note' => 'Existing athlete overlap',
        'active' => true,
    ]);

    expect(app(CalendarBlockService::class)->findNewCategoryOverlap(
        groupId: $group->id,
        categoryId: $category->id,
        start: Carbon::parse('2026-07-08'),
        end: Carbon::parse('2026-08-15'),
        userId: $athlete->id,
        parentId: $parentBlock->id,
        currentBlockId: $parentBlock->id,
    ))->toBeNull();
});

it('rejects an edit that introduces a new athlete overlap', function () {
    $group = UserGroup::create(['name' => 'New Overlap']);
    $athlete = User::factory()->athlete()->create();
    $group->members()->attach($athlete);
    $category = Tag::factory()->withScope('training_category')->create();

    $parentBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-08-01',
        'end' => '2026-08-10',
        'note' => 'Shared block',
        'active' => true,
    ]);

    $newConflict = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-08-15',
        'end' => '2026-08-20',
        'note' => 'New athlete overlap',
        'active' => true,
    ]);

    expect(app(CalendarBlockService::class)->findNewCategoryOverlap(
        groupId: $group->id,
        categoryId: $category->id,
        start: Carbon::parse('2026-08-01'),
        end: Carbon::parse('2026-08-20'),
        userId: $athlete->id,
        parentId: $parentBlock->id,
        currentBlockId: $parentBlock->id,
    )?->id)->toBe($newConflict->id);
});
