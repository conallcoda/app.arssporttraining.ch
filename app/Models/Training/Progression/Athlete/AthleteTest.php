<?php

namespace App\Models\Training\Progression\Athlete;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Exercise\Exercise;
use App\Models\Training\Progression\Reference\RepPercentageTable;
use Carbon\Carbon;

class AthleteTest extends AbstractData
{
    public function __construct(
        public int $exerciseId,
        public int $reps,
        public float $weight,
        public ?float $value = null,
        public ?Carbon $testedAt = null,
    ) {
        $this->value = $this->getDerived1RM();
    }

    public function getDerived1RM(): float
    {
        $percentage = RepPercentageTable::getPercentage($this->reps);

        return $this->weight / $percentage;
    }

    public static function getFields(): array
    {
        return [
            FluxField::select('exerciseId')
                ->label('Exercise')
                ->options(Exercise::orderBy('name')->pluck('name', 'id')->toArray())
                ->searchable()
                ->required(),
            FluxField::number('reps')
                ->label('Reps')
                ->required()
                ->min(1)
                ->max(30)
                ->suffix('reps'),
            FluxField::number('weight')
                ->label('Weight')
                ->required()
                ->min(0)
                ->step(0.5)
                ->suffix('kg'),
            FluxField::number('value')
                ->label('Estimated 1RM')
                ->step(0.5)
                ->suffix('kg')
                ->disabled()
                ->computedFrom(['reps', 'weight']),
        ];
    }
}
