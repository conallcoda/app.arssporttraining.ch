<?php

namespace App\Training;

use App\Models\Training\TrainingRevisionBatch;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class TrainingModelAuditService
{
    /** @param array<string, mixed> $context */
    public function recordCreated(Model $model, string $domain, string $subjectName, array $context = []): void
    {
        $this->record(
            model: $model,
            domain: $domain,
            action: 'create_'.$subjectName,
            stateKey: $domain,
            beforeValue: 'missing',
            afterValue: 'present',
            beforePayload: [],
            afterPayload: $this->snapshot($model->getAttributes()),
            context: $context,
        );
    }

    /**
     * @param  list<string>|null  $only
     * @param  array<string, mixed>  $context
     */
    public function recordUpdated(
        Model $model,
        string $domain,
        string $subjectName,
        ?array $only = null,
        array $context = [],
    ): void {
        $changed = array_keys(Arr::except($model->getChanges(), ['updated_at']));

        if ($only !== null) {
            $changed = array_values(array_intersect($changed, $only));
        }

        if ($changed === []) {
            return;
        }

        $this->record(
            model: $model,
            domain: $domain,
            action: 'update_'.$subjectName,
            stateKey: $domain,
            beforeValue: 'present',
            afterValue: 'present',
            beforePayload: $this->snapshot($model->getRawOriginal()),
            afterPayload: $this->snapshot($model->getAttributes()),
            context: ['changed_fields' => $changed, ...$context],
        );
    }

    /** @param array<string, mixed> $context */
    public function recordDeleted(Model $model, string $domain, string $subjectName, array $context = []): void
    {
        $this->record(
            model: $model,
            domain: $domain,
            action: 'delete_'.$subjectName,
            stateKey: $domain,
            beforeValue: 'present',
            afterValue: 'deleted',
            beforePayload: $this->snapshot($model->getRawOriginal()),
            afterPayload: [],
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $beforePayload
     * @param  array<string, mixed>  $afterPayload
     * @param  array<string, mixed>  $context
     */
    public function recordPayloadChange(
        Model $owner,
        string $domain,
        string $action,
        string $stateKey,
        array $beforePayload,
        array $afterPayload,
        array $context = [],
    ): ?TrainingRevisionBatch {
        if ($beforePayload === $afterPayload) {
            return null;
        }

        $batch = app(TrainingStateRevisionService::class)->createBatch(
            owner: $owner,
            action: $action,
            domain: $domain,
            context: $context,
        );

        app(TrainingStateRevisionService::class)->recordStateChange(
            batch: $batch,
            subject: $owner,
            stateKey: $stateKey,
            beforeValue: 'present',
            afterValue: 'present',
            beforePayload: $this->snapshot($beforePayload),
            afterPayload: $this->snapshot($afterPayload),
        );

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $beforePayload
     * @param  array<string, mixed>  $afterPayload
     * @param  array<string, mixed>  $context
     */
    private function record(
        Model $model,
        string $domain,
        string $action,
        string $stateKey,
        string $beforeValue,
        string $afterValue,
        array $beforePayload,
        array $afterPayload,
        array $context,
    ): void {
        $batch = app(TrainingStateRevisionService::class)->createBatch(
            owner: $model,
            action: $action,
            domain: $domain,
            context: $context,
        );

        app(TrainingStateRevisionService::class)->recordStateChange(
            batch: $batch,
            subject: $model,
            stateKey: $stateKey,
            beforeValue: $beforeValue,
            afterValue: $afterValue,
            beforePayload: $beforePayload,
            afterPayload: $afterPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function snapshot(array $attributes): array
    {
        return collect($attributes)
            ->map(fn (mixed $value): mixed => $this->normalizeValue($value))
            ->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => Carbon::instance($value)->utc()->toIso8601String(),
            is_array($value) => array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value),
            default => $value,
        };
    }
}
