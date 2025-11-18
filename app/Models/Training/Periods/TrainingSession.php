<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Models\Training\Periods\Data\TrainingPeriodIdentity;
use App\Models\Training\Periods\Data\TrainingSessionCategoryData;
use App\Models\Training\Periods\Data\TrainingSessionPeriod;
use App\Models\Training\TrainingPeriod;

class TrainingSession extends TrainingPeriodData
{
    public static string $type = 'session';

    public function __construct(
        public TrainingPeriodIdentity $identity,
        public ?TrainingSessionPeriod $period = null,
        public ?TrainingSessionCategoryData $category = null,
        #[DataCollectionOf(TrainingExercise::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return $this->period?->label() ?? 'Session';
    }

    public static function fromModel(TrainingPeriod $model, array $extra = [])
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            identity: static::createIdentity($model),
            period: TrainingSessionPeriod::from($model->extra['period']),
            category: TrainingSessionCategoryData::from($model->extra['category'] ?? null),
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $model = new static(
            identity: static::createIdentity(),
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
