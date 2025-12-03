<?php

namespace App\Models\Training\Progression\Strategy\Rep;

use App\Models\Training\Progression\Config\ResolvedExerciseConfig;

class FixedRepStrategy implements RepStrategyInterface
{
    public function calculateSetReps(
        ResolvedExerciseConfig $config,
        int $weekIndex,
        int $setIndex,
        int $totalSets,
        ?float $setWeight = null,
        ?float $reference1RM = null,
    ): int {
        $repConfig = $config->repConfig;
        assert($repConfig instanceof FixedRepConfig);

        return $repConfig->reps;
    }

    public function getReference1RM(
        ResolvedExerciseConfig $config,
        float $derived1RM,
        float $target1RM,
        int $weekIndex,
    ): float {
        return $derived1RM;
    }

    public function getRepPattern(ResolvedExerciseConfig $config, int $weekIndex): array
    {
        $repConfig = $config->repConfig;
        assert($repConfig instanceof FixedRepConfig);

        $totalSets = $config->getSetCountForWeek($weekIndex);

        return array_fill(0, $totalSets, $repConfig->reps);
    }

    public function getFullBlockPattern(ResolvedExerciseConfig $config): array
    {
        $pattern = [];

        for ($week = 0; $week < $config->blockLength; $week++) {
            $pattern[$week] = $this->getRepPattern($config, $week);
        }

        return $pattern;
    }
}
