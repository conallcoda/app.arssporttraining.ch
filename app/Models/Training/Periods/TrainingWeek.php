<?php

namespace App\Models\Training\Periods;

use App\Data\Model\ModelIdentity;
use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;


class TrainingWeek extends TrainingPeriodData
{
    public function __construct(
        public ?ModelIdentity $identity,
        public TrainingSession $parent,
        public int $sequence,
        #[DataCollectionOf(TrainingSession::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return "Week {$this->sequence}";
    }
}
