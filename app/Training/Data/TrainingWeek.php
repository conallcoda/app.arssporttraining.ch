<?php

namespace App\Training\Data;

use App\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingWeek extends AbstractData
{
    public function __construct(
        #[DataCollectionOf(TrainingSession::class)]
        public array $sessions = [],
        public ?float $target = null,
    ) {}

    public function mapSessions(callable $transformer): self
    {
        return new self(
            sessions: array_map($transformer, $this->sessions, array_keys($this->sessions)),
            target: $this->target,
        );
    }

    public function withTarget(float $target): self
    {
        return new self(
            sessions: $this->sessions,
            target: $target,
        );
    }

    public function lastSession(): ?TrainingSession
    {
        return $this->sessions[count($this->sessions) - 1] ?? null;
    }

    public static function fromSessionCount(int $sessionCount, int $setCount): self
    {
        $sessions = [];
        for ($i = 0; $i < $sessionCount; $i++) {
            $sessions[] = TrainingSession::fromSetCount($setCount);
        }

        return new self(sessions: $sessions);
    }
}
