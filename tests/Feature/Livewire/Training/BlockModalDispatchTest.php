<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\BlockForm;
use App\Livewire\Training\CalendarIndex;
use App\Livewire\Training\CalendarProgramsView;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Livewire\Livewire;

function blockWeekSettings(string $date = '2026-03-02'): CalendarSettingsData
{
    return new CalendarSettingsData(
        start: $date,
        end: Carbon::parse($date)->addDays(6)->format('Y-m-d'),
        preset: null,
    );
}

it('dispatches add-block payload from the programs view', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $tag = Tag::factory()->withScope('training_category')->create([
        'name' => 'Strength',
        'slug' => 'strength',
    ]);
    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $tag->id,
    ]);

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => blockWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('openAddBlock')
        ->assertDispatched('open-block', data: [
            'groupId' => $group->id,
            'userId' => $user->id,
            'categoryOptions' => [
                $tag->id => ['name' => 'Strength', 'slug' => 'strength'],
            ],
        ]);
});

it('dispatches athlete override payload when editing a category block in user mode', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $tag = Tag::factory()->withScope('training_category')->create();

    $parentBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-01',
        'end' => '2026-03-31',
        'note' => 'Parent',
        'active' => true,
    ]);

    $overrideBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'parent_id' => $parentBlock->id,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-05',
        'end' => '2026-03-31',
        'note' => 'Override',
        'active' => true,
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => blockWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('editBlock', $parentBlock->id)
        ->assertDispatched('open-block', function ($event, $params) use ($overrideBlock, $parentBlock, $group, $user) {
            return $params['data']['blockId'] === $overrideBlock->id
                && $params['data']['parentId'] === $parentBlock->id
                && $params['data']['groupId'] === $group->id
                && $params['data']['userId'] === $user->id;
        });
});

it('confirms before deleting a group block from athlete mode when the group has multiple athletes', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    [$athlete, $teammate] = User::factory()->athlete()->count(2)->create();
    $group->members()->attach([$athlete->id, $teammate->id]);

    Livewire::actingAs($coach)
        ->test(BlockForm::class)
        ->set('groupId', $group->id)
        ->set('userId', $athlete->id)
        ->set('parentBlockId', 123)
        ->assertSeeHtml('wire:click="requestGroupBlockDelete"')
        ->assertSee('Deleting this block will remove it for all athletes in the group.')
        ->call('requestGroupBlockDelete')
        ->assertNotDispatched('block.deleted')
        ->call('deleteGroupBlock')
        ->assertDispatched('block.deleted', data: [
            'editing_block_id' => 123,
            'groupId' => $group->id,
            'userId' => null,
            'parentId' => null,
        ]);
});

it('deletes a group block directly from athlete mode when the group has one athlete', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Solo']);
    $athlete = User::factory()->athlete()->create();
    $group->members()->attach($athlete);

    Livewire::actingAs($coach)
        ->test(BlockForm::class)
        ->set('groupId', $group->id)
        ->set('userId', $athlete->id)
        ->set('parentBlockId', 456)
        ->call('requestGroupBlockDelete')
        ->assertDispatched('block.deleted', data: [
            'editing_block_id' => 456,
            'groupId' => $group->id,
            'userId' => null,
            'parentId' => null,
        ]);
});

it('syncs group and athlete block dates when athlete mode edits a one-athlete group', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Solo']);
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
    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $athlete->id,
            'calendarSettings' => blockWeekSettings('2026-08-10'),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('onBlockSubmitted', [
            'groupId' => $group->id,
            'userId' => $athlete->id,
            'parentId' => $parentBlock->id,
            'editing_block_id' => null,
            'selected_members' => [],
            'categoryId' => $category->id,
            'type' => TrainingProgramBlockTypeEnum::Category->value,
            'start' => '2026-08-05',
            'end' => '2026-08-15',
            'note' => 'Athlete override',
            'color' => null,
            'config' => null,
        ]);

    $parentBlock->refresh();
    $athleteBlock = TrainingProgramBlock::query()
        ->where('parent_id', $parentBlock->id)
        ->where('user_id', $athlete->id)
        ->firstOrFail();

    expect($parentBlock->start->format('Y-m-d'))->toBe('2026-08-05')
        ->and($parentBlock->end?->format('Y-m-d'))->toBe('2026-08-15')
        ->and($athleteBlock->start->format('Y-m-d'))->toBe('2026-08-05')
        ->and($athleteBlock->end?->format('Y-m-d'))->toBe('2026-08-15');
});

it('dispatches add-block payload from the calendar index', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $tag = Tag::factory()->withScope('training_category')->create([
        'name' => 'Conditioning',
        'slug' => 'conditioning',
    ]);
    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $tag->id,
        'name' => 'Bike Session',
    ]);

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'group' => (string) $group->id,
        ])
        ->call('openAddBlock')
        ->assertDispatched('open-block', data: [
            'groupId' => $group->id,
            'userId' => null,
            'categoryOptions' => [
                $tag->id => ['name' => 'Conditioning', 'slug' => 'conditioning'],
            ],
        ]);
});

it('dispatches the shared add-program trigger from the calendar index', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'group' => (string) $group->id,
        ])
        ->call('openAddProgram')
        ->assertDispatched('trigger-add-content');
});

it('dispatches athlete override payload when opening plan block edit', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $tag = Tag::factory()->withScope('training_category')->create();
    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $tag->id,
        'name' => 'Strength A',
    ]);
    $trainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $parentBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-01',
        'end' => '2026-03-31',
        'note' => 'Parent',
        'active' => true,
    ]);

    $overrideBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'parent_id' => $parentBlock->id,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-02',
        'end' => '2026-03-31',
        'note' => 'Override',
        'active' => true,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'view' => 'plan',
            'group' => (string) $group->id,
            'user' => (string) $user->id,
            'planCategory' => (string) $tag->id,
            'planBlock' => (string) $parentBlock->id,
            'planProgram' => (string) $trainingProgram->id,
        ])
        ->call('openPlanBlockEdit')
        ->assertDispatched('open-block', function ($event, $params) use ($overrideBlock, $parentBlock, $group, $user) {
            return $params['data']['blockId'] === $overrideBlock->id
                && $params['data']['parentId'] === $parentBlock->id
                && $params['data']['groupId'] === $group->id
                && $params['data']['userId'] === $user->id;
        });
});

it('records provenance and state revisions when creating a block', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => blockWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('onBlockSubmitted', [
            'groupId' => $group->id,
            'userId' => $user->id,
            'selected_members' => [],
            'type' => 'focus',
            'start' => '2026-03-02',
            'end' => '2026-03-04',
            'note' => 'Peak week',
            'color' => '#112233',
        ]);

    $block = TrainingProgramBlock::query()->latest('id')->first();

    expect($block)->not->toBeNull()
        ->and($block?->created_by)->toBe($coach->id)
        ->and($block?->updated_by)->toBe($coach->id)
        ->and($block?->note)->toBe('Peak week')
        ->and(TrainingRevisionBatch::query()
            ->where('domain', 'state')
            ->where('action', 'save_block')
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramBlock::class)
            ->where('subject_id', $block?->id)
            ->where('state_key', 'block')
            ->where('after_value', 'created')
            ->where('changed_by', $coach->id)
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramBlock::class)
            ->where('subject_id', $block?->id)
            ->where('state_key', 'note')
            ->where('after_value', 'Peak week')
            ->where('changed_by', $coach->id)
            ->exists())->toBeTrue();
});

it('does not create overlapping category blocks in the same calendar lane', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $tag = Tag::factory()->withScope('training_category')->create([
        'name' => 'Strength',
        'slug' => 'strength',
    ]);
    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-01',
        'end' => '2026-03-10',
        'note' => 'Strength Block',
        'active' => true,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => null,
            'calendarSettings' => blockWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('onBlockSubmitted', [
            'groupId' => $group->id,
            'userId' => null,
            'selected_members' => [],
            'type' => 'category',
            'categoryId' => $tag->id,
            'start' => '2026-03-05',
            'end' => '2026-03-12',
            'note' => 'Archived Strength Block',
            'color' => null,
            'config' => [],
        ]);

    expect(TrainingProgramBlock::query()
        ->where('group_id', $group->id)
        ->where('category_id', $tag->id)
        ->count())->toBe(1);
});

it('records provenance and state revisions when deleting an athlete override block', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $tag = Tag::factory()->withScope('training_category')->create();
    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    $parentBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-01',
        'end' => '2026-03-31',
        'note' => 'Parent',
        'active' => true,
    ]);

    $overrideBlock = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'parent_id' => $parentBlock->id,
        'category_id' => $tag->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-03-05',
        'end' => '2026-03-31',
        'note' => 'Override',
        'active' => true,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => blockWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('onBlockDeleted', [
            'editing_block_id' => $overrideBlock->id,
            'groupId' => $group->id,
            'userId' => $user->id,
        ]);

    $overrideBlock = $overrideBlock->fresh();

    expect($overrideBlock)->not->toBeNull()
        ->and($overrideBlock?->active)->toBeFalse()
        ->and($overrideBlock?->updated_by)->toBe($coach->id)
        ->and(TrainingRevisionBatch::query()
            ->where('domain', 'state')
            ->where('action', 'delete_block')
            ->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()
            ->where('subject_type', TrainingProgramBlock::class)
            ->where('subject_id', $overrideBlock?->id)
            ->where('state_key', 'block')
            ->where('after_value', 'inactive')
            ->where('changed_by', $coach->id)
            ->exists())->toBeTrue();
});
