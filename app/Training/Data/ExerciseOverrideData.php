<?php

namespace App\Training\Data;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;

class ExerciseOverrideData extends AbstractData implements HasForms
{
    public const DEFAULT_TARGET = 7;

    public const DEFAULT_STARTING_REPS = 10;

    public const DEFAULT_SETS = 4;

    public function __construct(
        public ?float $target = null,
        public ?int $startingReps = null,
        public ?int $sets = null,
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
    ) {}

    public static function getFields(): array
    {
        return [
            FluxField::slider('target')
                ->label('Target (%)')
                ->min(1)
                ->max(15)
                ->step(0.5)
                ->ticks([2.5, 5, 7.5, 10, 12.5, 15])
                ->suffix('%')
                ->default(self::DEFAULT_TARGET),
            FluxField::slider('startingReps')
                ->label('Starting Reps')
                ->min(1)
                ->max(25)
                ->step(1)
                ->ticks([5, 10, 15, 20, 25])
                ->suffix('reps')
                ->default(self::DEFAULT_STARTING_REPS),
            FluxField::slider('sets')
                ->label('Sets')
                ->min(1)
                ->max(6)
                ->step(1)
                ->ticks([1, 2, 3, 4, 5, 6])
                ->suffix('sets')
                ->default(self::DEFAULT_SETS),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            target: $data['target'] ?? null,
            startingReps: isset($data['startingReps']) ? (int) $data['startingReps'] : null,
            sets: isset($data['sets']) ? (int) $data['sets'] : null,
            measuredReps: isset($data['measuredReps']) ? (int) $data['measuredReps'] : null,
            measuredWeight: isset($data['measuredWeight']) ? (float) $data['measuredWeight'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'target' => $this->target,
            'startingReps' => $this->startingReps,
            'sets' => $this->sets,
            'measuredReps' => $this->measuredReps,
            'measuredWeight' => $this->measuredWeight,
        ], fn ($value) => $value !== null);
    }

    public function getTarget(float $default): float
    {
        return $this->target ?? $default;
    }

    public function getStartingReps(int $default): int
    {
        return $this->startingReps ?? $default;
    }

    public function getSets(int $default): int
    {
        return $this->sets ?? $default;
    }
}
