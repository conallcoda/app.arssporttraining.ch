<?php

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingGroupScheduleMirrorService;

it('audits program definition creates updates and deletes with correlated context', function () {
    $coach = User::factory()->coach()->create();
    $this->actingAs($coach);

    $program = ExerciseProgram::factory()->create(['name' => 'Original']);
    $program->update(['name' => 'Renamed']);
    $program->delete();

    $batches = TrainingRevisionBatch::query()
        ->where('owner_type', ExerciseProgram::class)
        ->where('owner_id', $program->id)
        ->where('domain', 'definition')
        ->whereIn('action', [
            'create_exercise_program',
            'update_exercise_program',
            'delete_exercise_program',
        ])
        ->orderBy('id')
        ->get();

    $operationIds = $batches
        ->map(fn (TrainingRevisionBatch $batch): ?string => json_decode($batch->reason ?? '{}', true)['operation_id'] ?? null)
        ->unique()
        ->values();
    $updateBatch = $batches->firstWhere('action', 'update_exercise_program');
    $updateRevision = TrainingStateRevision::query()->where('batch_id', $updateBatch?->id)->first();

    expect($batches)->toHaveCount(3)
        ->and($batches->pluck('source')->unique()->values()->all())->toBe(['coach'])
        ->and($batches->pluck('changed_by')->unique()->values()->all())->toBe([$coach->id])
        ->and($operationIds)->toHaveCount(1)
        ->and($operationIds->first())->not->toBeNull()
        ->and($updateRevision?->before_payload['name'])->toBe('Original')
        ->and($updateRevision?->after_payload['name'])->toBe('Renamed');
});

it('audits direct schedule slot creates moves and deletes', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Audit Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $this->actingAs($coach);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
    ]);
    $slot->update(['datetime' => '2030-04-04 10:30:00']);
    $slot->delete();

    $batches = TrainingRevisionBatch::query()
        ->where('owner_type', TrainingProgramSlot::class)
        ->where('owner_id', $slot->id)
        ->where('domain', 'schedule')
        ->whereIn('action', [
            'create_training_program_slot',
            'update_training_program_slot',
            'delete_training_program_slot',
        ])
        ->orderBy('id')
        ->get();
    $moveBatch = $batches->firstWhere('action', 'update_training_program_slot');
    $moveRevision = TrainingStateRevision::query()->where('batch_id', $moveBatch?->id)->first();

    expect($batches)->toHaveCount(3)
        ->and($moveRevision?->before_payload['datetime'])->toBe('2030-04-03 09:00:00')
        ->and($moveRevision?->after_payload['datetime'])->toBe('2030-04-04 10:30:00')
        ->and(json_decode($moveBatch?->reason ?? '{}', true)['changed_fields'] ?? [])->toContain('datetime');
});

it('correlates mirrored schedule and definition changes', function () {
    $sourceGroup = UserGroup::create(['name' => 'Source']);
    $targetGroup = UserGroup::create(['name' => 'Target']);
    $athlete = User::factory()->athlete()->create();
    $program = ExerciseProgram::factory()->create(['name' => 'Mirrored Program']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $sourceGroup->id,
        'exercise_program_id' => $program->id,
    ]);
    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2030-04-03 09:00:00',
    ]);

    app(TrainingGroupScheduleMirrorService::class)->mirror($sourceGroup->id, $targetGroup->id);

    $scheduleBatch = TrainingRevisionBatch::query()
        ->where('action', 'mirror_group_schedule')
        ->latest('id')
        ->first();
    $definitionBatch = TrainingRevisionBatch::query()
        ->where('action', 'mirror_group_definitions')
        ->latest('id')
        ->first();
    $scheduleContext = json_decode($scheduleBatch?->reason ?? '{}', true);
    $definitionContext = json_decode($definitionBatch?->reason ?? '{}', true);

    expect($scheduleBatch)->not->toBeNull()
        ->and($definitionBatch)->not->toBeNull()
        ->and($scheduleContext['operation_id'] ?? null)->toBe($definitionContext['operation_id'] ?? null)
        ->and($definitionContext['source_group_id'] ?? null)->toBe($sourceGroup->id)
        ->and(TrainingStateRevision::query()->where('batch_id', $scheduleBatch?->id)->exists())->toBeTrue()
        ->and(TrainingStateRevision::query()->where('batch_id', $definitionBatch?->id)->exists())->toBeTrue();
});
