<?php

use App\Livewire\Training\CalendarIndex;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows inherited group programs when viewing a user', function () {
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

it('creates override with active=0 when toggling an inherited active slot', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $groupTp->id,
        'datetime' => '2026-04-01 09:00:00',
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00');

    $override = TrainingProgram::forUser($group->id, $user->id)
        ->where('exercise_program_id', $program->id)
        ->first();

    expect($override)->not->toBeNull();
    expect($override->slots)->toHaveCount(1);
    expect($override->slots->first()->active)->toBeFalse();
});

it('creates override with active=1 when toggling a non-existent inherited slot', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00');

    $override = TrainingProgram::forUser($group->id, $user->id)
        ->where('exercise_program_id', $program->id)
        ->first();

    expect($override)->not->toBeNull();
    expect($override->slots->first()->active)->toBeTrue();
});

it('removes override when toggling an existing override slot', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    TrainingProgramSlot::create([
        'training_program_id' => $groupTp->id,
        'datetime' => '2026-04-01 09:00:00',
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00')
        ->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00');

    $override = TrainingProgram::forUser($group->id, $user->id)
        ->where('exercise_program_id', $program->id)
        ->first();

    expect($override)->toBeNull();
});

it('cleans up empty override program when last slot is removed', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $component = Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00');

    expect(TrainingProgram::forUser($group->id, $user->id)->count())->toBe(1);

    $component->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00');

    expect(TrainingProgram::forUser($group->id, $user->id)->count())->toBe(0);
});

it('prevents removing an inherited group program from user view', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->call('removeTrainingProgram', $groupTp->id);

    expect(TrainingProgram::find($groupTp->id))->not->toBeNull();
});

it('prevents editing an inherited group program from user view', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id, 'user' => (string) $user->id])
        ->call('openEditProgram', $groupTp->id)
        ->assertNotDispatched('open-edit-program');
});

it('uses direct toggle logic in group view', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    Livewire::test(CalendarIndex::class, ['group' => (string) $group->id])
        ->call('toggleSlot', $groupTp->id, '2026-04-01 09:00:00');

    expect(TrainingProgramSlot::where('training_program_id', $groupTp->id)->count())->toBe(1);
    expect(TrainingProgram::where('exercise_program_id', $program->id)->where('user_id', '!=', null)->count())->toBe(0);
});

it('identifies group level programs correctly', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $userTp = TrainingProgram::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'exercise_program_id' => ExerciseProgram::factory()->create()->id,
    ]);

    expect($groupTp->isGroupLevel())->toBeTrue();
    expect($userTp->isGroupLevel())->toBeFalse();
});

it('finds or creates override sharing the same exercise program', function () {
    $group = UserGroup::create(['name' => 'Three Amigos']);
    $user = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create();

    $groupTp = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $override = TrainingProgram::findOrCreateOverride($groupTp, $user->id);

    expect($override->exercise_program_id)->toBe($program->id);
    expect($override->group_id)->toBe($group->id);
    expect($override->user_id)->toBe($user->id);

    $override2 = TrainingProgram::findOrCreateOverride($groupTp, $user->id);

    expect($override->id)->toBe($override2->id);
});
