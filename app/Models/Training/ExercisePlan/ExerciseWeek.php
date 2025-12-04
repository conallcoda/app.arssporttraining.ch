<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseWeek extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(ExerciseSession::class)]
        public array $sessions = [],
        public ?float $target = null,
    ) {}

    public function mapSessions(callable $transformer): self
    {
        return new self(
            sessions: array_map($transformer, $this->sessions, array_keys($this->sessions))
        );
    }

    public function lastSession(): ?ExerciseSession
    {
        return $this->sessions[count($this->sessions) - 1] ?? null;
    }

    public function lastSessionIndex(): int
    {
        return max(0, count($this->sessions) - 1);
    }

    public static function example(): self
    {
        return new self(
            sessions: [
                ExerciseSession::example(),
                ExerciseSession::example(),
            ],
        );
    }
}
