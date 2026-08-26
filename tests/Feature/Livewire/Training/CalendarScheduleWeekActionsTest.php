<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarScheduleView;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
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

it('removes a week only after confirmation', function () {
    Carbon::setTestNow('2030-04-10 12:00:00');

    [$coach, $athlete, $group] = scheduleWeekActionContext();

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Week To Clear'])->id,
    ]);

    $futureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-11 09:00:00',
    ]);

    $this->actingAs($coach);

    $component = Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-07', end: '2030-04-27', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])
        ->call('requestClearWeekSchedule', '2030-04-08')
        ->assertSet('pendingClearWeekStart', '2030-04-08');

    expect($futureSlot->fresh())->not->toBeNull();

    $component
        ->call('confirmClearWeekSchedule')
        ->assertSet('pendingClearWeekStart', null);

    expect($futureSlot->fresh())->toBeNull();
});

it('copies the current week to the next selected weeks after confirmation', function () {
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

    $weekOneTargetSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $targetProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-22 09:00:00',
    ]);
    $weekTwoTargetSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $targetProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-29 09:00:00',
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-14', end: '2030-05-12', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])
        ->call('requestCopyWeekSlotsForward', '2030-04-15', 2)
        ->assertSet('pendingCopySourceWeekStart', '2030-04-15')
        ->assertSet('pendingCopyForwardWeeks', 2)
        ->call('confirmCopyWeekSlotsForward')
        ->assertSet('pendingCopySourceWeekStart', null)
        ->assertSet('pendingCopyForwardWeeks', null);

    expect($weekOneTargetSlot->fresh())->toBeNull()
        ->and($weekTwoTargetSlot->fresh())->toBeNull()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-22 09:00:00',
        ])->exists())->toBeTrue()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-25 14:00:00',
        ])->exists())->toBeTrue()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-29 09:00:00',
        ])->exists())->toBeTrue()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-05-02 14:00:00',
        ])->exists())->toBeTrue()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-15 09:00:00',
        ])->exists())->toBeTrue();
});

it('does not copy over a partially completed target week and audits the rejection', function () {
    Carbon::setTestNow('2030-04-10 12:00:00');

    [$coach, $athlete, $group] = scheduleWeekActionContext();
    $sourceProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Source Week'])->id,
    ]);
    $targetProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Protected Target'])->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $sourceProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-15 09:00:00',
    ]);
    $protectedSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $targetProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-22 09:00:00',
        'status' => TrainingProgramSlotStatusEnum::PartiallyCompleted,
        'exercise_count' => 2,
        'completed_exercise_count' => 1,
        'pending_exercise_count' => 1,
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-14', end: '2030-05-05', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])
        ->call('requestCopyWeekSlotsForward', '2030-04-15', 1)
        ->call('confirmCopyWeekSlotsForward');

    $batch = TrainingRevisionBatch::query()->where('action', 'copy_schedule_forward')->latest('id')->first();
    $revision = TrainingStateRevision::query()->where('batch_id', $batch?->id)->first();

    expect($protectedSlot->fresh())->not->toBeNull()
        ->and(TrainingProgramSlot::query()->where([
            'training_program_id' => $sourceProgram->id,
            'user_id' => $athlete->id,
            'datetime' => '2030-04-22 09:00:00',
        ])->exists())->toBeFalse()
        ->and($batch?->domain)->toBe('schedule')
        ->and($batch?->changed_by)->toBe($coach->id)
        ->and($revision?->subject_id)->toBe($protectedSlot->id)
        ->and($revision?->after_payload['mutation_rejected'] ?? false)->toBeTrue();
});

it('does not clear a week containing a partially completed session', function () {
    Carbon::setTestNow('2030-04-10 12:00:00');

    [$coach, $athlete, $group] = scheduleWeekActionContext();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create(['name' => 'Protected Week'])->id,
    ]);
    $protectedSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-11 09:00:00',
        'status' => TrainingProgramSlotStatusEnum::PartiallyCompleted,
        'exercise_count' => 2,
        'completed_exercise_count' => 1,
        'pending_exercise_count' => 1,
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-07', end: '2030-04-27', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])->call('clearWeekSchedule', '2030-04-08');

    $batch = TrainingRevisionBatch::query()->where('action', 'clear_schedule_week')->latest('id')->first();
    $revision = TrainingStateRevision::query()->where('batch_id', $batch?->id)->first();

    expect($protectedSlot->fresh())->not->toBeNull()
        ->and($revision?->subject_id)->toBe($protectedSlot->id)
        ->and($revision?->after_payload['mutation_rejected'] ?? false)->toBeTrue();
});

function scheduleWeekActionContext(): array
{
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Schedule Week Actions']);
    $group->members()->attach($athlete);

    return [$coach, $athlete, $group];
}
