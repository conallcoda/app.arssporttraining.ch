<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarIndex;
use App\Livewire\Training\CalendarProgramsView;
use App\Livewire\Training\CalendarScheduleView;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function calendarWeekSettings(string $date = '2026-03-02'): CalendarSettingsData
{
    return new CalendarSettingsData(
        start: $date,
        end: Carbon::parse($date)->addDays(6)->format('Y-m-d'),
        preset: null,
    );
}

it('shows group programs when viewing a user', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);
    $program = ExerciseProgram::factory()->create(['name' => '1A Strength']);

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->assertSee('1A Strength');
});

it('can edit a group program from user view', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('openEditProgram', $groupTp->id)
        ->assertDispatched('open-edit-program');
});

it('filters out archived programs without scheduled slots and keeps scheduled archived programs visible', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $activeProgram = ExerciseProgram::factory()->create(['name' => '1A Strength']);
    $archivedScheduledProgram = ExerciseProgram::factory()->create(['name' => '1B Strength']);
    $archivedUnscheduledProgram = ExerciseProgram::factory()->create(['name' => 'Old Strength']);

    $activeTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $activeProgram->id,
    ]);

    $archivedScheduledTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $archivedScheduledProgram->id,
        'status' => TrainingProgram::STATUS_ARCHIVED,
    ]);

    TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $archivedUnscheduledProgram->id,
        'status' => TrainingProgram::STATUS_ARCHIVED,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $archivedScheduledTp->id,
        'user_id' => $user->id,
        'datetime' => '2026-03-03 09:00:00',
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
            'weekEndsOn' => ((int) config('training.week_starts_on', Carbon::MONDAY) + 6) % 7,
        ])
        ->assertSee('1A Strength')
        ->assertSee('1B Strength')
        ->assertSee('Archived')
        ->assertDontSee('Old Strength');

    expect($activeTp->fresh()->status)->toBeNull();
});

it('persists archived status through the edit program flow', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $program = ExerciseProgram::factory()->create(['name' => '1A Strength']);

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
            'weekEndsOn' => ((int) config('training.week_starts_on', Carbon::MONDAY) + 6) % 7,
        ])
        ->set('editingTrainingProgramId', $groupTp->id)
        ->call('handleEditProgramSubmitted', [
            'name' => '1A Strength Updated',
            'type' => 'program',
            'exercise_category_id' => null,
            'exercises' => [],
            'internalTags' => [],
            'sort' => 0,
            'status' => TrainingProgram::STATUS_ARCHIVED,
        ]);

    expect($groupTp->fresh()->status)->toBe(TrainingProgram::STATUS_ARCHIVED)
        ->and($program->fresh()->name)->toBe('1A Strength Updated');
});

it('duplicates a calendar program as a fresh clone with all exercise sections', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $category = Tag::factory()->withScope('exercise_category')->create();
    $programTag = Tag::factory()->withScope('program_internal')->create();
    $warmUpExercise = \App\Models\Exercise\Exercise::factory()->create(['name' => 'Warm Up Drill']);
    $mainExercise = \App\Models\Exercise\Exercise::factory()->create(['name' => 'Main Lift']);
    $warmDownExercise = \App\Models\Exercise\Exercise::factory()->create(['name' => 'Cool Down Reset']);

    $program = ExerciseProgram::factory()->create([
        'name' => '1A Strength',
        'exercise_category_id' => $category->id,
        'owner_id' => $coach->id,
    ]);
    $program->tags()->attach($programTag->id);

    $sourcePivots = collect([
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $warmUpExercise->id,
            'sort' => 0,
            'type' => 'warm_up',
        ]),
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $mainExercise->id,
            'sort' => 0,
            'type' => 'main',
        ]),
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $warmDownExercise->id,
            'sort' => 0,
            'type' => 'warm_down',
        ]),
    ]);

    $sourceTrainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
        'status' => TrainingProgram::STATUS_ARCHIVED,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
            'weekEndsOn' => ((int) config('training.week_starts_on', Carbon::MONDAY) + 6) % 7,
        ])
        ->call('openDuplicateProgram', $sourceTrainingProgram->id)
        ->assertDispatched('open-edit-program', function ($event, $params) use ($sourceTrainingProgram) {
            return ($params['title'] ?? null) === 'Duplicate Program'
                && ($params['data']['name'] ?? null) === ''
                && array_key_exists('id', $params['data'])
                && $params['data']['id'] === null
                && ($params['data']['_duplicate_source_training_program_id'] ?? null) === $sourceTrainingProgram->id;
        })
        ->call('handleEditProgramSubmitted', [
            '_duplicate_source_training_program_id' => $sourceTrainingProgram->id,
            'name' => '1B Strength',
            'type' => 'program',
            'exercise_category_id' => $category->id,
            'owner_id' => $coach->id,
            'internalTags' => [$programTag->id],
            'status' => TrainingProgram::STATUS_ACTIVE,
        ]);

    $duplicateTrainingProgram = TrainingProgram::query()
        ->where('group_id', $group->id)
        ->where('id', '!=', $sourceTrainingProgram->id)
        ->firstOrFail();
    $duplicateProgram = ExerciseProgram::with('internalTags')->findOrFail($duplicateTrainingProgram->exercise_program_id);
    $duplicatePivots = ExerciseProgramExercise::query()
        ->where('exercise_program_id', $duplicateProgram->id)
        ->orderBy('type')
        ->orderBy('sort')
        ->get();

    expect($duplicateProgram->name)->toBe('1B Strength')
        ->and($duplicateProgram->parent_type)->toBe(TrainingProgram::class)
        ->and((int) $duplicateProgram->parent_id)->toBe($duplicateTrainingProgram->id)
        ->and($duplicateProgram->exercise_category_id)->toBe($category->id)
        ->and($duplicateProgram->internalTags->pluck('id')->all())->toBe([$programTag->id])
        ->and($duplicateTrainingProgram->status)->toBeNull()
        ->and($duplicatePivots)->toHaveCount(3)
        ->and($duplicatePivots->pluck('type')->sort()->values()->all())->toBe(['main', 'warm_down', 'warm_up'])
        ->and($duplicatePivots->pluck('exercise_id')->sort()->values()->all())->toBe($sourcePivots->pluck('exercise_id')->sort()->values()->all())
        ->and($duplicatePivots->pluck('id')->intersect($sourcePivots->pluck('id'))->isEmpty())->toBeTrue();
});

it('can remove a group program from user view', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('removeTrainingProgram', $groupTp->id);

    expect(TrainingProgram::find($groupTp->id))->toBeNull();
});

it('can still remove a group program when past sessions exist but nothing was recorded', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');

    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $groupTp->id,
        'user_id' => $user->id,
        'datetime' => '2026-03-03 09:00:00',
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('removeTrainingProgram', $groupTp->id);

    expect(TrainingProgram::find($groupTp->id))->toBeNull();
});

it('blocks removing a group program once a past session has recorded data', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');

    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $groupTp->id,
        'user_id' => $user->id,
        'datetime' => '2026-03-03 09:00:00',
        'status' => \App\Models\Training\TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => '2026-03-03 10:00:00',
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => $weekStartsOn,
            'weekEndsOn' => ($weekStartsOn + 6) % 7,
        ])
        ->call('removeTrainingProgram', $groupTp->id)
        ->assertDispatched('toast-show', function ($event, $params) {
            return ($params['slots']['text'] ?? null) === 'This program cannot be changed here because 1 session already has recorded data.'
                && ($params['dataset']['variant'] ?? null) === 'danger';
        });

    expect(TrainingProgram::find($groupTp->id))->not->toBeNull();
});

it('marks only recorded sessions as locked in plan schedule info', function () {
    Carbon::setTestNow('2026-03-08 12:00:00');

    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);
    $category = Tag::factory()->withScope('exercise_category')->create([
        'name' => 'Mobility',
        'sort_order' => 1,
    ]);
    $program = ExerciseProgram::factory()->create([
        'exercise_category_id' => $category->id,
    ]);
    $exercise = Exercise::factory()->create();

    $trainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $user->id,
        'datetime' => '2026-03-03 09:00:00',
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $user->id,
        'datetime' => '2026-03-05 09:00:00',
        'status' => TrainingProgramSlotStatusEnum::PartiallyCompleted,
        'has_any_modification' => true,
        'partial_exercise_count' => 1,
    ]);

    $slotWithRecordedChild = TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $user->id,
        'datetime' => '2026-03-06 09:00:00',
        'status' => TrainingProgramSlotStatusEnum::Pending,
        'has_any_modification' => false,
        'completed_exercise_count' => 0,
        'partial_exercise_count' => 0,
        'skipped_exercise_count' => 0,
    ]);
    $slotExercise = TrainingProgramSlotExercise::create([
        'training_program_slot_id' => $slotWithRecordedChild->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
        'status' => TrainingProgramSlotExerciseStatusEnum::Pending,
        'set_count' => 1,
        'completed_set_count' => 0,
        'modified_set_count' => 0,
        'skipped_set_count' => 0,
        'pending_set_count' => 1,
        'has_any_modification' => false,
    ]);
    TrainingProgramSlotSet::create([
        'training_program_slot_exercise_id' => $slotExercise->id,
        'set_number' => 1,
        'status' => TrainingProgramSlotSetStatusEnum::Completed,
        'completed_at' => '2026-03-06 10:00:00',
        'has_any_modification' => false,
    ]);

    $component = Livewire::actingAs($coach)
        ->test(CalendarIndex::class, [
            'group' => (string) $group->id,
            'user' => (string) $user->id,
            'view' => 'plan',
            'planCategory' => (string) $category->id,
            'planProgram' => (string) $trainingProgram->id,
        ]);

    $info = $component->get('planScheduleInfo');

    expect($info['lockedSessionsByWeek'] ?? [])->toBe([[false, true, true]])
        ->and($info['sessionStatusesByWeek'][0][0]['value'] ?? null)->toBe(TrainingProgramSlotStatusEnum::Pending->value)
        ->and($info['sessionStatusesByWeek'][0][0]['label'] ?? null)->toBe('Pending')
        ->and($info['sessionStatusesByWeek'][0][1]['value'] ?? null)->toBe(TrainingProgramSlotStatusEnum::PartiallyCompleted->value)
        ->and($info['sessionStatusesByWeek'][0][1]['label'] ?? null)->toBe('Partially Completed')
        ->and($info['sessionStatusesByWeek'][0][2]['value'] ?? null)->toBe(TrainingProgramSlotStatusEnum::Pending->value);
});

it('creates individual slots per user in group mode', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user1 = User::factory()->athlete()->create();
    $user2 = User::factory()->athlete()->create();
    $group->members()->attach([$user1->id, $user2->id]);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => $group->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
        ])
        ->call('onWeekSlotSubmitted', [
            'training_program_id' => $tp->id,
            'date' => '2026-03-02',
            'start_time' => '09:00',
            'selected_members' => [$user1->id, $user2->id],
            'deselected_members' => [],
            'original_training_program_id' => null,
            'original_start_time' => null,
        ]);

    expect(TrainingProgramSlot::where('training_program_id', $tp->id)->count())->toBe(2);
    expect(TrainingProgramSlot::where('user_id', $user1->id)->exists())->toBeTrue();
    expect(TrainingProgramSlot::where('user_id', $user2->id)->exists())->toBeTrue();
});

it('deselecting a user removes their slot', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user1 = User::factory()->athlete()->create();
    $user2 = User::factory()->athlete()->create();
    $group->members()->attach([$user1->id, $user2->id]);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $datetime = '2026-03-02 09:00:00';
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user1->id, 'datetime' => $datetime]);
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user2->id, 'datetime' => $datetime]);

    Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => $group->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
        ])
        ->call('onWeekSlotSubmitted', [
            'training_program_id' => $tp->id,
            'date' => '2026-03-02',
            'start_time' => '09:00',
            'selected_members' => [$user1->id],
            'deselected_members' => [$user2->id],
            'original_training_program_id' => $tp->id,
            'original_start_time' => '09:00',
        ]);

    expect(TrainingProgramSlot::where('user_id', $user1->id)->exists())->toBeTrue();
    expect(TrainingProgramSlot::where('user_id', $user2->id)->exists())->toBeFalse();
});

it('removes all user slots in group mode', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user1 = User::factory()->athlete()->create();
    $user2 = User::factory()->athlete()->create();
    $group->members()->attach([$user1->id, $user2->id]);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $datetime = '2026-03-02 09:00:00';
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user1->id, 'datetime' => $datetime]);
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user2->id, 'datetime' => $datetime]);

    Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => $group->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
        ])
        ->call('quickRemoveWeekSlot', $tp->id, '2026-03-02', '09:00');

    expect(TrainingProgramSlot::where('training_program_id', $tp->id)->count())->toBe(0);
});

it('removes only the user slot in user mode', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user1 = User::factory()->athlete()->create();
    $user2 = User::factory()->athlete()->create();
    $group->members()->attach([$user1->id, $user2->id]);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $datetime = '2026-03-02 09:00:00';
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user1->id, 'datetime' => $datetime]);
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user2->id, 'datetime' => $datetime]);

    Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => $group->id,
            'userId' => $user1->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
        ])
        ->call('quickRemoveWeekSlot', $tp->id, '2026-03-02', '09:00');

    expect(TrainingProgramSlot::where('user_id', $user1->id)->exists())->toBeFalse();
    expect(TrainingProgramSlot::where('user_id', $user2->id)->exists())->toBeTrue();
});

it('numbers athlete program slots from the full block instead of the visible date range', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $category = Tag::factory()->create(['scope' => 'training_category']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Bali Programm Armando',
        'exercise_category_id' => $category->id,
    ]);

    $trainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-05-01',
        'end' => '2026-05-31',
        'note' => 'May Block',
        'active' => true,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $user->id,
        'datetime' => '2026-05-01 09:00:00',
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $user->id,
        'datetime' => '2026-05-04 09:00:00',
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarProgramsView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => new CalendarSettingsData(
                start: '2026-05-04',
                end: '2026-05-10',
                preset: null,
            ),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
            'weekEndsOn' => ((int) config('training.week_starts_on', Carbon::MONDAY) + 6) % 7,
        ])
        ->assertSet("athleteSlotOrder.{$trainingProgram->id}-2026-05-04", 2);
});

it('user view only shows that user slots', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user1 = User::factory()->athlete()->create();
    $user2 = User::factory()->athlete()->create();
    $group->members()->attach([$user1->id, $user2->id]);

    $program = ExerciseProgram::factory()->create(['name' => 'Strength A']);
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $datetime = '2026-03-02 09:00:00';
    TrainingProgramSlot::create(['training_program_id' => $tp->id, 'user_id' => $user2->id, 'datetime' => $datetime]);

    $component = Livewire::test(CalendarIndex::class, [
        'group' => (string) $group->id,
        'user' => (string) $user1->id,
        'period' => 'week',
        'date' => '2026-03-02',
    ]);

    expect(TrainingProgramSlot::where('user_id', $user1->id)->exists())->toBeFalse();
});

it('quick creates a slot for a single user in user mode', function () {
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => $group->id,
            'userId' => $user->id,
            'calendarSettings' => calendarWeekSettings(),
            'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
            'weekEditMode' => 'edit',
        ])
        ->set('quickProgramId', $tp->id)
        ->call('quickCreateWeekSlot', '2026-03-02', 'am');

    expect(TrainingProgramSlot::where('training_program_id', $tp->id)->where('user_id', $user->id)->count())->toBe(1);
});
