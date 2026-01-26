<?php

namespace App\Livewire\Training\View;

use App\Data\AbstractData;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseType\StrengthDefaults;
use App\Models\TrainingPlanProgramExercise;

class ProgramExerciseOverrideData extends AbstractData
{
    public function __construct(
        public int $id,
        public int $exercise_id,
        public string $name,
        public ?int $oneRepMaxModifier,
        public ?int $startingReps,
        public ?string $timeUnderTension,
        public ?int $rest,
    ) {}

    public static function fromPivot(TrainingPlanProgramExercise $pivot): self
    {
        $exercise = $pivot->exercise;
        $defaults = StrengthDefaults::fromExercise($exercise);

        return new self(
            id: $pivot->id,
            exercise_id: $pivot->exercise_id,
            name: $exercise->name,
            oneRepMaxModifier: $pivot->extra['oneRepMaxModifier'] ?? null,
            startingReps: $pivot->extra['startingReps'] ?? null,
            timeUnderTension: $pivot->extra['timeUnderTension'] ?? null,
            rest: $pivot->extra['rest'] ?? null,
        );
    }

    public static function getDefaults(Exercise $exercise): array
    {
        $defaults = StrengthDefaults::fromExercise($exercise);

        return [
            'oneRepMaxModifier' => $defaults->oneRepMaxModifier,
            'startingReps' => $defaults->startingReps,
            'timeUnderTension' => $defaults->timeUnderTension,
            'rest' => $defaults->rest,
        ];
    }
}
