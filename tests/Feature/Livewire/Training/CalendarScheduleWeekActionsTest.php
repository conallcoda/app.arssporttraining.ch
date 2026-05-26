<?php

use App\Data\Training\Calendar\CalendarSettingsData;
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

afterEach(function (): void {
    Carbon::setTestNow();
});

it('copies a source week into a target week without touching past target dates', function () {
    Carbon::setTestNow('2030-04-10 12:00:00');

    [$coach, $athlete, $group] = scheduleWeekActionContext();

    $sourceProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Source Week'])->id,
    ]);
    $targetProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Target Week'])->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $sourceProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-15 09:00:00',
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $sourceProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-18 14:00:00',
    ]);

    $pastTargetSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $targetProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-08 09:00:00',
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $targetProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-10 14:00:00',
    ]);
    $futureTargetSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $targetProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-11 14:00:00',
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-07', end: '2030-04-27', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])->call('copyWeekSlots', '2030-04-15', '2030-04-08');

    expect($pastTargetSlot->fresh())->not->toBeNull()
        ->and($futureTargetSlot->fresh())->toBeNull()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $targetProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-10 14:00:00',
        ])->exists())->toBeFalse()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-11 14:00:00',
        ])->exists())->toBeTrue()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-08 09:00:00',
        ])->exists())->toBeFalse();
});

it('deletes only future sessions from the selected week', function () {
    Carbon::setTestNow('2030-04-10 12:00:00');

    [$coach, $athlete, $group] = scheduleWeekActionContext();

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Week To Clear'])->id,
    ]);

    $pastSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-08 09:00:00',
    ]);
    $futureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-11 09:00:00',
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-07', end: '2030-04-27', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])->call('clearWeekSchedule', '2030-04-08');

    expect($pastSlot->fresh())->not->toBeNull()
        ->and($futureSlot->fresh())->toBeNull();
});

function scheduleWeekActionContext(): array
{
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Schedule Week Actions']);
    $group->members()->attach($athlete);

    return [$coach, $athlete, $group];
}
