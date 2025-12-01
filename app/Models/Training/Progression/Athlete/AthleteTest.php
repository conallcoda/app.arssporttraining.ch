<?php

namespace App\Models\Training\Progression\Athlete;

use App\Data\AbstractData;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use Carbon\Carbon;

class AthleteTest extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public int $reps,
        public float $weight,
        public ?Carbon $testedAt = null,
    ) {}

    public function getDerived1RM(): float
    {
        $percentage = RepPercentageTable::getPercentage($this->reps);

        return $this->weight / $percentage;
    }
}
