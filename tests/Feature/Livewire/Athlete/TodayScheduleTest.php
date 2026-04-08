<?php

use App\Livewire\Athlete\AthleteLayout;
use App\Livewire\Athlete\DaySchedule;
use App\Livewire\Athlete\Record;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->group = UserGroup::create(['name' => 'Test Group']);
    $this->today = Carbon::today()->format('Y-m-d');
});

function createSlotForAthlete($athlete, $group, array $overrides = []): TrainingProgramSlot
{
    $trainingProgram = TrainingProgram::factory()->create(
        array_merge(['group_id' => $group->id], isset($overrides['exercise_program_id']) ? ['exercise_program_id' => $overrides['exercise_program_id']] : [])
    );

    return TrainingProgramSlot::factory()->create([
        'training_program_id' => $overrides['training_program_id'] ?? $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => $overrides['datetime'] ?? Carbon::today()->setTime(9, 0),
    ]);
}

it('shows empty state when athlete has no schedule', function () {
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertSee('Nothing scheduled')
        ->assertDontSee('Readiness');
});

it('shows readiness card and hides training when no score', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertSee('Readiness')
        ->assertDontSee('Training')
        ->assertSee('Please fill in the readiness survey');
});

it('shows training when readiness score is provided', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today, 'readinessScore' => 4])
        ->assertSee('Readiness')
        ->assertSee('Training');
});

it('hides readiness when showReadiness is false', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today, 'showReadiness' => false])
        ->assertDontSee('Readiness')
        ->assertSee('Training');
});

it('groups programs into am section', function () {
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Morning Strength']);
    createSlotForAthlete($athlete, $this->group, [
        'exercise_program_id' => $program->id,
        'datetime' => Carbon::today()->setTime(9, 0),
    ]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today, 'readinessScore' => 4])
        ->assertSee('Morning Strength')
        ->assertSee('AM');
});

it('groups programs into pm section', function () {
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Afternoon Cardio']);
    createSlotForAthlete($athlete, $this->group, [
        'exercise_program_id' => $program->id,
        'datetime' => Carbon::today()->setTime(14, 0),
    ]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today, 'readinessScore' => 4])
        ->assertSee('Afternoon Cardio')
        ->assertSee('PM');
});

it('shows correct readiness labels', function (int $score, string $label) {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today, 'readinessScore' => $score, 'readinessLabel' => $label])
        ->assertSee($label);
})->with([
    [4, 'Ready'],
    [3, 'Train Smart'],
    [2, 'Recovery'],
    [1, 'Rest'],
]);

it('shows Check In button when no score', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertSee('Check In');
});

it('does not show slots from other dates', function () {
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Yesterday Program']);
    createSlotForAthlete($athlete, $this->group, [
        'exercise_program_id' => $program->id,
        'datetime' => Carbon::yesterday()->setTime(9, 0),
    ]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertDontSee('Yesterday Program')
        ->assertSee('Nothing scheduled');
});

it('does not show slots belonging to other users', function () {
    $athlete = User::factory()->athlete()->create();
    $otherAthlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Other User Program']);
    createSlotForAthlete($otherAthlete, $this->group, [
        'exercise_program_id' => $program->id,
    ]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertDontSee('Other User Program')
        ->assertSee('Nothing scheduled');
});

it('shows correct program count', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group, ['datetime' => Carbon::today()->setTime(9, 0)]);
    createSlotForAthlete($athlete, $this->group, ['datetime' => Carbon::today()->setTime(14, 0)]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertSee('2');
});

it('works with a future date for tomorrow view', function () {
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Tomorrow Program']);
    $tomorrow = Carbon::tomorrow()->format('Y-m-d');

    createSlotForAthlete($athlete, $this->group, [
        'exercise_program_id' => $program->id,
        'datetime' => Carbon::tomorrow()->setTime(9, 0),
    ]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $tomorrow, 'showReadiness' => false])
        ->assertSee('Tomorrow Program')
        ->assertDontSee('Readiness');
});

it('athlete layout stores readiness on submission event', function () {
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($athlete)
        ->test(AthleteLayout::class)
        ->assertSet('readinessScore', null)
        ->dispatch('readiness-submitted', score: 4)
        ->assertSet('readinessScore', 4)
        ->assertDispatched('readiness-updated', score: 4);
});

it('record starts with null readiness', function () {
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($athlete)
        ->test(Record::class)
        ->assertSet('readinessScore', null);
});

it('record updates readiness on readiness-updated event', function () {
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($athlete)
        ->test(Record::class)
        ->assertSet('readinessScore', null)
        ->dispatch('readiness-updated', score: 4)
        ->assertSet('readinessScore', 4);
});

it('day schedule updates readiness on readiness-updated event', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertSet('readinessScore', null)
        ->dispatch('readiness-updated', score: 3)
        ->assertSet('readinessScore', 3);
});
