<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarIndex;
use App\Livewire\Training\CalendarProgramsView;
use App\Livewire\Training\CalendarScheduleView;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
        'weekStartsOn' => $weekStartsOn,
        'weekEndsOn' => ($weekStartsOn + 6) % 7,
    ])
        ->call('openEditProgram', $groupTp->id)
        ->assertDispatched('open-edit-program');
});

it('can remove a group program from user view', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);

    Livewire::test(CalendarProgramsView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
        'weekStartsOn' => $weekStartsOn,
        'weekEndsOn' => ($weekStartsOn + 6) % 7,
    ])
        ->call('removeTrainingProgram', $groupTp->id);

    expect(TrainingProgram::find($groupTp->id))->toBeNull();
});

it('creates individual slots per user in group mode', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user1 = User::factory()->athlete()->create();
    $user2 = User::factory()->athlete()->create();
    $group->members()->attach([$user1->id, $user2->id]);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
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

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
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

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
        'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
    ])
        ->call('quickRemoveWeekSlot', $tp->id, '2026-03-02', '09:00');

    expect(TrainingProgramSlot::where('training_program_id', $tp->id)->count())->toBe(0);
});

it('removes only the user slot in user mode', function () {
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

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $user1->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
        'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
    ])
        ->call('quickRemoveWeekSlot', $tp->id, '2026-03-02', '09:00');

    expect(TrainingProgramSlot::where('user_id', $user1->id)->exists())->toBeFalse();
    expect(TrainingProgramSlot::where('user_id', $user2->id)->exists())->toBeTrue();
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
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $group->members()->attach($user);

    $program = ExerciseProgram::factory()->create();
    $tp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $user->id,
        'calendarSettings' => new CalendarSettingsData(period: 'week', date: '2026-03-02'),
        'weekStartsOn' => (int) config('training.week_starts_on', Carbon::MONDAY),
        'weekEditMode' => 'edit',
    ])
        ->set('quickProgramId', $tp->id)
        ->call('quickCreateWeekSlot', '2026-03-02', 'am');

    expect(TrainingProgramSlot::where('training_program_id', $tp->id)->where('user_id', $user->id)->count())->toBe(1);
});
