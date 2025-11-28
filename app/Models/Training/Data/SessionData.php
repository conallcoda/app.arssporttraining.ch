<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class SessionData extends TrainingData
{
    public function __construct(
        public ?string $name = null,
        public int $day = 0,
        public int $slot = 0,
        public ?int $category = null,
        public array $exercises = [],
    ) {}

    public function name(TrainingNode $node): string
    {
        return $this->name ?? "Session " . ($node->sequence + 1);
    }

    static public function getModelType(): string
    {
        return 'session';
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
