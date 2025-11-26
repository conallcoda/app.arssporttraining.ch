<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class LinkedSessionData extends TrainingData
{
    public function __construct(
        public int $day = 0,
        public int $slot = 0,
    ) {}

    public function name(TrainingNode $node): string
    {
        return "Linked Session " . ($node->sequence + 1);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'day' => $this->day,
            'slot' => $this->slot,
            'category' => $this->category,
            'exercises' => $this->exercises,
        ];
    }


    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            name: $model->extra->name ?? null,
            day: $model->extra->day,
            slot: $model->extra->slot,
            category: $model->extra->category,
            exercises: $model->extra->exercises ?? [],
        );
        return $instance;
    }
}
