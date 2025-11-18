<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\Data\TrainingSessionCategory;
use App\Models\Training\Periods\Data\TrainingSessionPeriod;

class TrainingSession extends TrainingPeriodData
{
    public static string $type = 'session';

    public function __construct(
        public TrainingWeek $parent,
        public ?ModelIdentity $identity = null,
        public TrainingSessionPeriod $period,
        public ?TrainingSessionCategory $category,
        #[DataCollectionOf(TrainingExercise::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return $this->period->label();
    }

    public static function fromConfig(array $data)
    {
        $model = new static(
            parent: $data['parent'],
            identity: $data['identity'] ?? null,
            period: TrainingSessionPeriod::from($data['period']),
            category: TrainingSessionCategory::from($data['category']),
        );

        return static::passParentAndSquence($model, $data);
    }

    public static function getModelType(): string
    {
        return 'session';
    }

    public static function getChildClass(): ?string
    {
        return TrainingExercise::class;
    }
}
