<?php

namespace App\Models\Training\Progression\Config;

use App\Data\AbstractData;
use App\Models\Training\Progression\Casts\RepConfigCast;
use App\Models\Training\Progression\Casts\WeightConfigCast;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use App\Models\Training\Progression\Strategy\Rep\RepConfigInterface;
use App\Models\Training\Progression\Strategy\Weight\WeightConfigInterface;
use Spatie\LaravelData\Attributes\WithCast;

class ResolvedExerciseConfig extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public int $blockLength,
        #[WithCast(WeightConfigCast::class)]
        public WeightConfigInterface $weightConfig,
        #[WithCast(RepConfigCast::class)]
        public RepConfigInterface $repConfig,
        public float $modifier = 1.0,
    ) {}

    public function getAnchorRepsForWeek(int $weekIndex): int
    {
        return $this->repConfig->getAnchorRepsForWeek($weekIndex, $this->blockLength);
    }

    public function getSetCountForWeek(int $weekIndex): int
    {
        return ($weekIndex % 2 === 0) ? 3 : 4;
    }

    public function getRepPercentage(int $reps): float
    {
        return RepPercentageTable::getPercentage($reps);
    }

    public function getTarget1RM(float $derived1RM): float
    {
        return $derived1RM * (1 + $this->weightConfig->getTargetImprovementAsDecimal());
    }
}
