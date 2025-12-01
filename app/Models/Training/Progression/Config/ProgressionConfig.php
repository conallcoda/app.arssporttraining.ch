<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ProgressionConfig extends AbstractData
{
    public function __construct(
        public string $weightStrategy = 'fixed_step',
        public string $repStrategy = 'paired_ladder',
        public float $targetImprovement = 0.125,
        public int $startingReps = 14,
        public int $stepDownInterval = 2,
        public int $repDecrement = 2,
        public int $minimumReps = 6,
        public float $incrementStep = 0.5,
        public int $blockLength = 5,
        #[DataCollectionOf(ExerciseConfig::class)]
        public array $exerciseOverrides = [],
    ) {}

    public function forExercise(int $exerciseId): ResolvedExerciseConfig
    {
        $override = $this->getExerciseOverride($exerciseId);

        return new ResolvedExerciseConfig(
            exerciseId: $exerciseId,
            weightStrategy: $override?->weightStrategy ?? $this->weightStrategy,
            repStrategy: $override?->repStrategy ?? $this->repStrategy,
            targetImprovement: $override?->targetImprovement ?? $this->targetImprovement,
            startingReps: $override?->startingReps ?? $this->startingReps,
            stepDownInterval: $override?->stepDownInterval ?? $this->stepDownInterval,
            repDecrement: $override?->repDecrement ?? $this->repDecrement,
            minimumReps: $override?->minimumReps ?? $this->minimumReps,
            incrementStep: $override?->incrementStep ?? $this->incrementStep,
            blockLength: $this->blockLength,
            modifier: $override?->modifier ?? 1.0,
        );
    }

    public function getExerciseOverride(int $exerciseId): ?ExerciseConfig
    {
        return $this->exerciseOverrides[$exerciseId] ?? null;
    }

    public function setExerciseOverride(int $exerciseId, string $key, mixed $value): static
    {
        if (! isset($this->exerciseOverrides[$exerciseId])) {
            $this->exerciseOverrides[$exerciseId] = new ExerciseConfig(exerciseId: $exerciseId);
        }

        $this->exerciseOverrides[$exerciseId]->{$key} = $value;

        return $this;
    }

    public function clearExerciseOverride(int $exerciseId, ?string $key = null): static
    {
        if ($key === null) {
            unset($this->exerciseOverrides[$exerciseId]);
        } elseif (isset($this->exerciseOverrides[$exerciseId])) {
            $this->exerciseOverrides[$exerciseId]->{$key} = null;
        }

        return $this;
    }

    public function hasExerciseOverride(int $exerciseId, ?string $key = null): bool
    {
        $override = $this->exerciseOverrides[$exerciseId] ?? null;

        if ($override === null) {
            return false;
        }

        if ($key === null) {
            return true;
        }

        return $override->hasOverride($key);
    }
}
