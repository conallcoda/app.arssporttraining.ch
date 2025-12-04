<?php

namespace App\Models\Training\ExercisePlan\Rules;

use App\Data\Form\FluxField;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use App\Models\Training\ExercisePlan\ExerciseSet;

class RoundWeightsToNearestStep extends BlockRule
{
    public function __construct(
        public float $step = 0.5,
    ) {}

    public static function getFields(): array
    {
        return [
            FluxField::number('step')
                ->label('Rounding Step')
                ->required()
                ->min(0.25)
                ->max(10)
                ->step(0.25)
                ->suffix('kg')
                ->rules('required|numeric|min:0.25|max:10'),
        ];
    }

    public function apply(ExerciseBlock $block): ExerciseBlock
    {
        return $block->mapWeeks(
            fn ($week) => $week->mapSessions(
                fn ($session) => $session->mapSets(
                    fn (ExerciseSet $set) => new ExerciseSet(
                        reps: $set->reps,
                        weight: $set->weight !== null
                            ? round($set->weight / $this->step) * $this->step
                            : null,
                        oneRepMax: $set->oneRepMax,
                    )
                )
            )
        );
    }
}
