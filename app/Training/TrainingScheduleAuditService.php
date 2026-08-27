<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingRevisionBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TrainingScheduleAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function start(Model $owner, string $action, array $context = []): TrainingRevisionBatch
    {
        $auditContext = app(TrainingAuditContext::class);

        return TrainingRevisionBatch::create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'domain' => 'schedule',
            'action' => $action,
            'changed_by' => auth()->id(),
            'source' => $auditContext->source(),
            'reason' => $auditContext->reason($context),
        ]);
    }

    /**
     * @param  Collection<int, TrainingProgramSlot>  $before
     * @param  Collection<int, TrainingProgramSlot>  $after
     */
    public function recordChanges(TrainingRevisionBatch $batch, Collection $before, Collection $after): void
    {
        $beforeById = $before->keyBy(fn (TrainingProgramSlot $slot): int => (int) $slot->id);
        $afterById = $after->keyBy(fn (TrainingProgramSlot $slot): int => (int) $slot->id);
        $slotIds = $beforeById->keys()->merge($afterById->keys())->unique();

        foreach ($slotIds as $slotId) {
            $beforeSlot = $beforeById->get($slotId);
            $afterSlot = $afterById->get($slotId);
            $subject = $afterSlot ?? $beforeSlot;

            if (! $subject instanceof TrainingProgramSlot) {
                continue;
            }

            app(TrainingStateRevisionService::class)->recordStateChange(
                batch: $batch,
                subject: $subject,
                stateKey: 'schedule',
                beforeValue: $beforeSlot instanceof TrainingProgramSlot ? 'present' : 'missing',
                afterValue: $afterSlot instanceof TrainingProgramSlot ? 'present' : 'deleted',
                beforePayload: $beforeSlot instanceof TrainingProgramSlot ? $this->snapshot($beforeSlot) : [],
                afterPayload: $afterSlot instanceof TrainingProgramSlot ? $this->snapshot($afterSlot) : [],
            );
        }
    }

    /**
     * @param  Collection<int, TrainingProgramSlot>  $slots
     */
    public function recordRejected(TrainingRevisionBatch $batch, Collection $slots, string $reason): void
    {
        $context = json_decode($batch->reason ?? '{}', true);
        $batch->forceFill([
            'reason' => json_encode([
                ...(is_array($context) ? $context : []),
                'outcome' => 'rejected',
                'rejection_reason' => $reason,
            ], JSON_THROW_ON_ERROR),
        ])->saveQuietly();

        foreach ($slots->unique('id') as $slot) {
            $snapshot = $this->snapshot($slot);

            app(TrainingStateRevisionService::class)->recordStateChange(
                batch: $batch,
                subject: $slot,
                stateKey: 'schedule',
                beforeValue: 'present',
                afterValue: 'present',
                beforePayload: $snapshot,
                afterPayload: $snapshot + [
                    'mutation_rejected' => true,
                    'rejection_reason' => $reason,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, TrainingProgramSlot>  $slots
     * @param  array<string, mixed>  $context
     */
    public function reject(
        Model $owner,
        string $action,
        Collection $slots,
        string $reason,
        array $context = [],
    ): TrainingRevisionBatch {
        $batch = $this->start($owner, $action, $context);
        $this->recordRejected($batch, $slots, $reason);

        return $batch;
    }

    /** @return array<string, mixed> */
    public function snapshot(TrainingProgramSlot $slot): array
    {
        return [
            'id' => (int) $slot->id,
            'training_program_id' => (int) $slot->training_program_id,
            'user_id' => (int) $slot->user_id,
            'owner_id' => $slot->owner_id === null ? null : (int) $slot->owner_id,
            'datetime' => $slot->datetime?->toDateTimeString(),
            'scheduled_date' => $slot->scheduled_date?->format('Y-m-d'),
            'session_index' => $slot->session_index === null ? null : (int) $slot->session_index,
            'status' => $slot->status?->value ?? (string) $slot->status,
            'exercise_count' => (int) $slot->exercise_count,
            'completed_exercise_count' => (int) $slot->completed_exercise_count,
            'partial_exercise_count' => (int) $slot->partial_exercise_count,
            'skipped_exercise_count' => (int) $slot->skipped_exercise_count,
            'pending_exercise_count' => (int) $slot->pending_exercise_count,
            'has_any_modification' => (bool) $slot->has_any_modification,
            'completed_at' => $slot->completed_at?->toIso8601String(),
            'cancelled_at' => $slot->cancelled_at?->toIso8601String(),
            'created_at' => $slot->created_at?->toIso8601String(),
            'updated_at' => $slot->updated_at?->toIso8601String(),
        ];
    }
}
