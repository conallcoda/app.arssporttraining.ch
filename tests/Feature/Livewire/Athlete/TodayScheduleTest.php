<?php

use App\Livewire\Athlete\AthleteLayout;
use App\Livewire\Athlete\DaySchedule;
use App\Livewire\Athlete\ReadinessCheck;
use App\Livewire\Athlete\Record;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
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

    $exerciseProgram = $trainingProgram->program;

    if ($exerciseProgram !== null && $exerciseProgram->exercises()->count() === 0) {
        $exerciseProgram->exercises()->attach(Exercise::factory()->create()->id, [
            'sort' => 0,
            'type' => 'main',
        ]);
    }

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
        ->assertSee('09:00')
        ->assertDontSee('Nothing scheduled')
        ->assertDontSee('Please fill in the readiness survey');
});

it('hides readiness when showReadiness is false', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today, 'showReadiness' => false])
        ->assertDontSee('Readiness')
        ->assertSee('09:00')
        ->assertDontSee('Nothing scheduled');
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
        ->assertSee('AM')
        ->assertSee('09:00');
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
        ->assertSee('PM')
        ->assertSee('14:00');
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
        ->assertSee('AM')
        ->assertSee('09:00')
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

it('loads persisted readiness for the selected date', function () {
    $athlete = User::factory()->athlete()->create();
    createSlotForAthlete($athlete, $this->group);

    $submission = MetricSubmission::create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::Readiness,
        'recorded_by' => $athlete->id,
        'recorded_at' => $this->today,
        'owner_type' => User::class,
        'owner_id' => $athlete->id,
    ]);

    $submission->values()->createMany([
        ['field' => 'sleepMinutes', 'value' => '450'],
        ['field' => 'sleepQuality', 'value' => '4'],
        ['field' => 'altitudeMeters', 'value' => '1800'],
        ['field' => 'condition', 'value' => '4'],
        ['field' => 'mood', 'value' => '4'],
        ['field' => 'motivation', 'value' => '4'],
        ['field' => 'soreness', 'value' => '4'],
        ['field' => 'energy', 'value' => '4'],
        ['field' => 'restingHeartRate', 'value' => '48'],
        ['field' => 'restingHeartRateBaseline', 'value' => '46'],
        ['field' => 'readinessScore', 'value' => '4.0571428571429'],
        ['field' => 'trafficLight', 'value' => 'ready'],
        ['field' => 'trafficLightLabel', 'value' => 'Ready'],
        ['field' => 'trafficLightColor', 'value' => 'green'],
    ]);

    Livewire::actingAs($athlete)
        ->test(DaySchedule::class, ['date' => $this->today])
        ->assertSet('readinessScore', 4.142857142857143)
        ->assertSet('readinessLabel', 'Ready')
        ->assertSet('readinessColor', 'green');
});

it('submits the full readiness survey and persists it as a metric', function () {
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($athlete)
        ->test(ReadinessCheck::class, ['date' => $this->today])
        ->set('form.sleepMinutes', 450)
        ->set('form.sleepQuality', 4)
        ->set('form.altitudeMeters', 1800)
        ->set('form.condition', 4)
        ->set('form.mood', 4)
        ->set('form.motivation', 5)
        ->set('form.soreness', 4)
        ->set('form.energy', 4)
        ->set('form.restingHeartRate', 48)
        ->set('form.restingHeartRateBaseline', 46)
        ->set('form.hrv', null)
        ->call('submitReadiness')
        ->assertDispatched('readiness-updated');

    $submission = MetricSubmission::query()
        ->where('user_id', $athlete->id)
        ->where('metric', \App\Data\Athlete\Metric\MetricEnum::Readiness)
        ->with('values')
        ->first();

    expect($submission)->not->toBeNull()
        ->and($submission->values->pluck('value', 'field')->has('hrv'))->toBeFalse()
        ->and($submission->values->pluck('value', 'field')->get('trafficLight'))->toBe('ready')
        ->and((float) $submission->values->pluck('value', 'field')->get('readinessScore'))->toBeGreaterThan(0);
});

it('requires resting heart rate but allows missing hrv when submitting readiness', function () {
    $athlete = User::factory()->athlete()->create();

    Livewire::actingAs($athlete)
        ->test(ReadinessCheck::class, ['date' => $this->today])
        ->set('form.restingHeartRate', null)
        ->set('form.hrv', null)
        ->call('submitReadiness')
        ->assertHasErrors([
            'form.restingHeartRate' => ['required'],
        ]);
});
