<?php

namespace App\Models\Training\Periods;

use App\Data\Model\ModelIdentity;
use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingWeek extends TrainingPeriodData
{
    public function __construct(
        public TrainingBlock $parent,
        public ?ModelIdentity $identity = null,
        public int $sequence = 0,
        #[DataCollectionOf(TrainingSession::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return "Week " . ($this->sequence + 1);
    }

    public static function fromConfig(array $data)
    {
        $model = new static(
            parent: $data['parent'],
            sequence: $data['sequence'],
            identity: $data['identity'] ?? null,
        );
        return static::passParent($model, $data);
    }

    public static function getModelType(): string
    {
        return 'week';
    }
    public static function getChildClass(): ?string
    {
        return TrainingSession::class;
    }
}
