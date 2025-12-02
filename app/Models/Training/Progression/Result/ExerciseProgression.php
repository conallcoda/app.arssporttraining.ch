<?php

namespace App\Models\Training\Progression\Result;

use App\Data\AbstractData;
use App\Models\Training\Progression\Config\ResolvedExerciseConfig;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class ExerciseProgression extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public float $derived1RM,
        public float $target1RM,
        public ResolvedExerciseConfig $config,
        #[DataCollectionOf(WeekResult::class)]
        public array $weeks = [],
    ) {}

    public function getWeek(int $weekIndex): ?WeekResult
    {
        return $this->weeks[$weekIndex] ?? null;
    }

    public function weekCount(): int
    {
        return count($this->weeks);
    }

    public function getImprovementPercentage(): float
    {
        return $this->config->weightConfig->targetImprovement;
    }

    public function getCompletedWeekCount(): int
    {
        $count = 0;

        foreach ($this->weeks as $week) {
            if ($week->isFullyLocked()) {
                $count++;
            }
        }

        return $count;
    }

    public function getProgressPercentage(): float
    {
        $total = $this->weekCount();

        if ($total === 0) {
            return 0;
        }

        return ($this->getCompletedWeekCount() / $total) * 100;
    }

    public function getTotalVolume(): float
    {
        $volume = 0;

        foreach ($this->weeks as $week) {
            $volume += $week->getTotalVolume();
        }

        return $volume;
    }

    public function getAnchorProgression(): array
    {
        return array_map(
            fn (WeekResult $week) => [
                'week' => $week->weekIndex + 1,
                'weight' => $week->anchorWeight,
                'reps' => $week->anchorReps,
            ],
            $this->weeks
        );
    }
}
