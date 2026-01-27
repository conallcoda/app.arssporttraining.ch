<?php

namespace App\Livewire\Training\View;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseType\StrengthDefaults;
use App\Models\TrainingPlanProgramExercise;

class ProgramExerciseData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public ?int $training_plan_program_id,
        public ?int $exercise_id,
        public string $name,
        public int $sort,
        public int $oneRepMaxModifier,
        public int $startingReps,
        public string $timeUnderTension,
        public int $rest,
    ) {}

    public static function fromPivot(TrainingPlanProgramExercise $pivot): self
    {
        $exercise = $pivot->exercise;
        $defaults = StrengthDefaults::fromExercise($exercise);

        return new self(
            id: $pivot->id,
            training_plan_program_id: $pivot->training_plan_program_id,
            exercise_id: $pivot->exercise_id,
            name: $exercise->name,
            sort: $pivot->sort ?? 0,
            oneRepMaxModifier: $pivot->extra['oneRepMaxModifier'] ?? $defaults->oneRepMaxModifier,
            startingReps: $pivot->extra['startingReps'] ?? $defaults->startingReps,
            timeUnderTension: $pivot->extra['timeUnderTension'] ?? $defaults->timeUnderTension,
            rest: $pivot->extra['rest'] ?? $defaults->rest,
        );
    }

    public static function fromExercise(Exercise $exercise, int $programId, int $sort = 0): self
    {
        $defaults = StrengthDefaults::fromExercise($exercise);

        return new self(
            id: null,
            training_plan_program_id: $programId,
            exercise_id: $exercise->id,
            name: $exercise->name,
            sort: $sort,
            oneRepMaxModifier: $defaults->oneRepMaxModifier,
            startingReps: $defaults->startingReps,
            timeUnderTension: $defaults->timeUnderTension,
            rest: $defaults->rest,
        );
    }

    public function persist(): void
    {
        $extra = [
            'oneRepMaxModifier' => $this->oneRepMaxModifier,
            'startingReps' => $this->startingReps,
            'timeUnderTension' => $this->timeUnderTension,
            'rest' => $this->rest,
        ];

        if ($this->id === null) {
            $pivot = TrainingPlanProgramExercise::create([
                'training_plan_program_id' => $this->training_plan_program_id,
                'exercise_id' => $this->exercise_id,
                'sort' => $this->sort,
                'extra' => $extra,
            ]);
            $this->id = $pivot->id;
        } else {
            $pivot = TrainingPlanProgramExercise::findOrFail($this->id);
            $pivot->exercise_id = $this->exercise_id;
            $pivot->sort = $this->sort;
            $pivot->extra = $extra;
            $pivot->save();
        }
    }

    public static function getFields(): array
    {
        $exerciseOptions = Exercise::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($exercise) => [$exercise->id => $exercise->name])
            ->all();

        return [
            FluxField::select('exercise_id')
                ->label('Exercise')
                ->options($exerciseOptions)
                ->placeholder('Select exercise')
                ->required()
                ->rules('required|exists:exercises,id'),
            FluxField::percentage('oneRepMaxModifier')
                ->label('1RM (Modifier)')
                ->default(100),
            FluxField::number('startingReps')
                ->label('Starting Reps')
                ->default(12)
                ->min(1)
                ->suffix('rep(s)')
                ->step(1),
            FluxField::tut('timeUnderTension')
                ->default('03-01-03-01')
                ->label('Time Under Tension'),
            FluxField::number('rest')
                ->label('Rest Between Sets')
                ->default(30)
                ->min(0)
                ->suffix('s')
                ->step(5),
        ];
    }

    public function getSettingsBadges(): array
    {
        return [
            [
                'label' => $this->oneRepMaxModifier.' %',
                'modalField' => 'oneRepMaxModifier',
            ],
            [
                'label' => $this->startingReps.' rep(s)',
                'modalField' => 'startingReps',
            ],
            [
                'label' => $this->timeUnderTension,
                'modalField' => 'timeUnderTension',
            ],
            [
                'label' => $this->rest.' s',
                'modalField' => 'rest',
            ],
        ];
    }
}
