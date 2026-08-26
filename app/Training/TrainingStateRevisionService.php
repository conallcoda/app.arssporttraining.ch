<?php

namespace App\Training;

use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
use Illuminate\Database\Eloquent\Model;

class TrainingStateRevisionService
{
    /** @param array<string, mixed> $context */
    public function createBatch(
        Model $owner,
        string $action,
        string $domain = 'state',
        array $context = [],
        ?string $source = null,
    ): TrainingRevisionBatch {
        $auditContext = app(TrainingAuditContext::class);

        return TrainingRevisionBatch::create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'domain' => $domain,
            'action' => $action,
            'changed_by' => auth()->id(),
            'source' => $source ?? $auditContext->source(),
            'reason' => $auditContext->reason($context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $beforePayload
     * @param  array<string, mixed>  $afterPayload
     */
    public function recordStateChange(
        TrainingRevisionBatch $batch,
        Model $subject,
        string $stateKey,
        ?string $beforeValue,
        ?string $afterValue,
        array $beforePayload = [],
        array $afterPayload = [],
    ): ?TrainingStateRevision {
        if ($beforeValue === $afterValue && $beforePayload === $afterPayload) {
            return null;
        }

        return TrainingStateRevision::create([
            'batch_id' => $batch->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'state_key' => $stateKey,
            'before_value' => $beforeValue,
            'after_value' => $afterValue,
            'before_payload' => $beforePayload === [] ? null : $beforePayload,
            'after_payload' => $afterPayload === [] ? null : $afterPayload,
            'changed_by' => auth()->id(),
            'source' => $batch->source ?? app(TrainingAuditContext::class)->source(),
        ]);
    }
}
