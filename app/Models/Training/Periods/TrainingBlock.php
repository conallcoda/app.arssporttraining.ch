<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Data\Model\ModelIdentity;

class TrainingBlock extends TrainingPeriodData
{
    public static string $type = 'block';

    public function __construct(
        public ?ModelIdentity $identity,
        public int $sequence,
        #[DataCollectionOf(TrainingWeek::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return "Block {$this->sequence}";
    }
}
