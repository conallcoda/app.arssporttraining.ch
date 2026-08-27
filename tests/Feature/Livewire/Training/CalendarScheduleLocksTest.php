<?php

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\CalendarScheduleView;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('does not delete a recorded slot from the schedule view modal and shows a toast', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $group->members()->attach($athlete);

    $exerciseProgram = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-10 10:00:00'),
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-01', end: '2030-04-30', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])
        ->call('onWeekSlotDeleted', [
            'training_program_id' => $trainingProgram->id,
            'date' => '2030-04-10',
            'start_time' => '09:00',
            'deletion_confirmed' => true,
        ])
        ->assertDispatched('toast-show', function ($event, $params) {
            return ($params['slots']['text'] ?? null) === 'This session already has recorded data and can no longer be edited.'
                && ($params['dataset']['variant'] ?? null) === 'danger';
        });

    expect($slot->fresh())->not->toBeNull();
});

it('does not delete a recorded slot from remove mode and shows a toast', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $group->members()->attach($athlete);

    $exerciseProgram = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-10 10:00:00'),
    ]);

    $this->actingAs($coach);

    Livewire::test(CalendarScheduleView::class, [
        'groupId' => $group->id,
        'userId' => $athlete->id,
        'calendarSettings' => new CalendarSettingsData(start: '2030-04-01', end: '2030-04-30', preset: null),
        'weekStartsOn' => Carbon::MONDAY,
    ])
        ->call('quickRemoveWeekSlot', $trainingProgram->id, '2030-04-10', '09:00')
        ->assertDispatched('toast-show', function ($event, $params) {
            return ($params['slots']['text'] ?? null) === 'This session already has recorded data and can no longer be edited.'
                && ($params['dataset']['variant'] ?? null) === 'danger';
        });

    expect($slot->fresh())->not->toBeNull();
});

it('rejects an unconfirmed occurrence deletion and audits it', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Confirmation Required']);
    $group->members()->attach($athlete);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => ExerciseProgram::factory()->create()->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-10 09:00:00',
    ]);

    Livewire::actingAs($coach)
        ->test(CalendarScheduleView::class, [
            'groupId' => $group->id,
            'userId' => $athlete->id,
            'calendarSettings' => new CalendarSettingsData(start: '2030-04-01', end: '2030-04-30', preset: null),
            'weekStartsOn' => Carbon::MONDAY,
        ])
        ->call('onWeekSlotDeleted', [
            'training_program_id' => $trainingProgram->id,
            'date' => '2030-04-10',
            'start_time' => '09:00',
            'deletion_confirmed' => false,
        ]);

    $batch = TrainingRevisionBatch::query()->where('action', 'delete_occurrence')->latest('id')->first();
    $context = json_decode($batch?->reason ?? '{}', true);

    expect($slot->fresh())->not->toBeNull()
        ->and($context['outcome'] ?? null)->toBe('rejected')
        ->and($context['rejection_reason'] ?? null)->toBe('deletion_confirmation_required');
});
