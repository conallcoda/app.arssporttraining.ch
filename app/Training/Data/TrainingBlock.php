<?php

namespace App\Training\Data;

use App\Cms\Data\AbstractData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingBlock extends AbstractData
{
    public function __construct(
        public ExerciseConfig $config,
        #[DataCollectionOf(TrainingWeek::class)]
        public array $weeks = [],
    ) {}

    public function mapWeeks(callable $transformer): self
    {
        return new self(
            config: $this->config,
            weeks: array_map($transformer, $this->weeks, array_keys($this->weeks))
        );
    }

    public function withWeeks(array $weeks): self
    {
        return new self(
            config: $this->config,
            weeks: $weeks,
        );
    }

    public function lastWeek(): ?TrainingWeek
    {
        return $this->weeks[count($this->weeks) - 1] ?? null;
    }

    public function lastWeekIndex(): int
    {
        return max(0, count($this->weeks) - 1);
    }
}
