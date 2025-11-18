<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\Data\TrainingSessionCategoryData;
use App\Models\Training\Periods\Data\TrainingSessionPeriod;
use App\Models\Training\TrainingPeriod;

class TrainingSession extends TrainingPeriodData
{
    public static string $type = 'session';

    public function __construct(
        public TrainingWeek $parent,
        public ?ModelIdentity $identity = null,
        public TrainingSessionPeriod $period,
        public ?TrainingSessionCategoryData $category,
        #[DataCollectionOf(TrainingExercise::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return $this->period->label();
    }

    public static function fromModel(TrainingPeriod $model, array $extra = [])
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            parent: $extra['parent'],
            identity: ModelIdentity::fromModel($model),
            period: TrainingSessionPeriod::from($model->extra['period']),
            category: TrainingSessionCategoryData::from($model->extra['category'] ?? null),
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $model = new static(
            parent: $data['parent'],
            identity: $data['identity'] ?? null,
            period: TrainingSessionPeriod::from($data['period']),
            category: TrainingSessionCategoryData::from($data['category']),
        );

        return static::passParentAndSequence($model, $data);
    }

    public static function getModelType(): string
    {
        return 'session';
    }

    public function getModelData(): array
    {
        return [
            'extra' => [
                'period' => $this->period->toArray(),
                'category' => $this->category?->identity?->id,
            ]
        ];
    }

    public static function getChildClass(): ?string
    {
        return TrainingExercise::class;
    }
}
