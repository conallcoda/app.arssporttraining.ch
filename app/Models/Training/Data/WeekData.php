<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class WeekData extends TrainingData
{
    public function __construct(
        public array $progressionOverrides = [],
    ) {}

    public function name(TrainingNode $node): string
    {
        return "Week " . ($node->sequence + 1);
    }

    static public function getModelType(): string
    {
        return 'week';
    }

    public function toArray(): array
    {
        return [
            'progressionOverrides' => $this->progressionOverrides,
        ];
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            progressionOverrides: (array) ($model->extra->progressionOverrides ?? []),
        );
        return $instance;
    }
}
