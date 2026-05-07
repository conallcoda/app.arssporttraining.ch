<?php

namespace App\Training;

use App\Models\Training\TrainingRevisionBatch;
use App\Models\Training\TrainingStateRevision;
use App\Models\Users\UserTypeEnum;
use Illuminate\Database\Eloquent\Model;

class TrainingStateRevisionService
{
    public function createBatch(Model $owner, string $action): TrainingRevisionBatch
    {
        return TrainingRevisionBatch::create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'domain' => 'state',
            'action' => $action,
            'changed_by' => auth()->id(),
            'source' => $this->resolveSource(),
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
            'source' => $this->resolveSource(),
        ]);
    }

    private function resolveSource(): string
    {
        $type = auth()->user()?->type;

        return match ($type) {
            UserTypeEnum::Coach => 'coach',
            UserTypeEnum::Admin => 'admin',
            default => 'athlete',
        };
    }
}
