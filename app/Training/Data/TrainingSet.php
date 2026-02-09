<?php

namespace App\Training\Data;

use App\Cms\Data\AbstractData;

class TrainingSet extends AbstractData
{
    public function __construct(
        public ?int $reps = null,
        public ?float $weight = null,
        public ?float $oneRepMax = null,
        public ?int $duration = null,
        public ?int $distance = null,
        public ?string $pace = null,
        public ?int $watts = null,
    ) {}

    public function withOverrides(array $values): self
    {
        return new self(
            reps: $values['reps'] ?? $this->reps,
            weight: $values['weight'] ?? $this->weight,
            oneRepMax: $this->oneRepMax,
            duration: $values['duration'] ?? $this->duration,
            distance: $values['distance'] ?? $this->distance,
            pace: $values['pace'] ?? $this->pace,
            watts: $values['watts'] ?? $this->watts,
        );
    }
}
