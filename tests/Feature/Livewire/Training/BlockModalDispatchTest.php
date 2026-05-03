<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarIndex;
use App\Livewire\Training\CalendarProgramsView;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
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
