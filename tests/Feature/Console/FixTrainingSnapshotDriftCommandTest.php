<?php

use App\Console\Commands\FixTrainingSnapshotDriftCommand;
use App\Data\Training\Audit\ScheduledSnapshotAuditResultData;
use App\Data\Training\Audit\ScheduledSnapshotClassificationData;
use App\Data\Training\Audit\ScheduledSnapshotMismatchData;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\ScheduledTrainingSnapshotRemediationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('repairs future-open snapshot drift and verifies that predicted outcomes match afterwards', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Fix Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Fix Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan(FixTrainingSnapshotDriftCommand::SIGNATURE, ['--slot-id' => [$slot->id]])
        ->expectsOutputToContain('Training snapshot drift remediation complete.')
        ->expectsOutputToContain('Backfill rebuilt slots: 1')
        ->expectsOutputToContain('Compare mismatched future-open slots: 0')
        ->assertExitCode(0);

    $weight = $slot->fresh('exercises.sets.values')->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

    expect((float) $weight->planned_decimal_value)->toBe(82.5);
});

it('fails when future-open mismatches remain after remediation', function () {
    $this->app->instance(ScheduledTrainingSnapshotRemediationService::class, new class extends ScheduledTrainingSnapshotRemediationService
    {
        public function __construct() {}

        public function remediate(
            ?int $trainingProgramId = null,
            ?int $userId = null,
            ?string $fromDate = null,
            ?string $toDate = null,
            array $slotIds = [],
        ): array {
            return [
                'backfill' => [
                    'audited_slots' => 1,
                    'eligible_slots' => 1,
                    'rebuilt_slots' => 1,
                    'matching_slots' => 0,
                    'skipped_locked_past' => 0,
                    'skipped_ambiguous' => 0,
                    'skipped_future_filter' => 0,
                ],
                'compare' => [
                    'compared_slots' => 1,
                    'matching_slots' => 0,
                    'mismatched_slots' => 1,
                    'results' => [
                        new ScheduledSnapshotAuditResultData(
                            slotId: 999,
                            classification: new ScheduledSnapshotClassificationData('future_open'),
                            matches: false,
                            mismatchCount: 1,
                            mismatches: [
                                new ScheduledSnapshotMismatchData(
                                    path: 'exercise:1|0||main.set:1.value:weight.planned_decimal_value',
                                    kind: 'planned_decimal_value',
                                    expected: 82.5,
                                    actual: 85.0,
                                ),
                            ],
                        ),
                    ],
                ],
            ];
        }
    });

    $this->artisan(FixTrainingSnapshotDriftCommand::SIGNATURE, ['--slot-id' => [999]])
        ->expectsOutputToContain('Training snapshot drift remediation complete.')
        ->expectsOutputToContain('Compare mismatched future-open slots: 1')
        ->expectsOutputToContain('Future-open snapshot mismatches remain after remediation.')
        ->expectsOutputToContain('Slot 999 mismatches: 1 [future_open]')
        ->assertExitCode(1);
});
