<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\Data\TrainingSessionCategory;

class TrainingSession extends TrainingPeriodData
{
    public static string $type = 'session';

    public function __construct(
        public ?ModelIdentity $identity,
        public TrainingWeek $parent,
        public TrainingSessionCategory $category,
        public int $sequence,
        #[DataCollectionOf(TrainingExercise::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return "Session {$this->sequence}";
    }
}
