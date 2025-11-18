<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class SessionData extends TrainingData
{
    public function __construct(
        public int $day,
        public int $slot,
        public ?int $category,
    ) {}

    public function name(TrainingNode $node): string
    {
        return "Session " . ($node->sequence + 1);
    }
    static public function getModelType(): string
    {
        return 'session';
    }

    public function toArray(): array
    {
        return [
            'day' => $this->day,
            'slot' => $this->slot,
            'category' => $this->category,
        ];
    }


    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            day: $model->extra->day,
            slot: $model->extra->slot,
            category: $model->extra->category
        );
        return $instance;
    }
}
