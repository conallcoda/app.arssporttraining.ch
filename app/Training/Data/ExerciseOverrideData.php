<?php

namespace App\Training\Data;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Training\Program\Sets;
use App\Form\Fields\Training\Program\StartingReps;
use App\Form\Fields\Training\Program\Target;

class ExerciseOverrideData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public const DEFAULT_TARGET = 7;

    public const DEFAULT_STARTING_REPS = 10;

    public const DEFAULT_SETS = 4;

    public const DEFAULT_TEMPO = '3010';

    public const DEFAULT_REST = 30;

    public function __construct(
        public ?float $target = null,
        public ?int $startingReps = null,
        public ?int $sets = null,
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?string $tempo = null,
        public ?int $rest = null,
    ) {}

    public static function getForm(): array
    {
        return [
            Target::make('target'),
            StartingReps::make('startingReps'),
            Sets::make('sets'),
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
            tempo: $data['tempo'] ?? null,
            rest: isset($data['rest']) ? (int) $data['rest'] : null,
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
            'tempo' => $this->tempo,
            'rest' => $this->rest,
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

    public function getTempo(string $default): string
    {
        return $this->tempo ?? $default;
    }

    public function getRest(int $default): int
    {
        return $this->rest ?? $default;
    }
}
