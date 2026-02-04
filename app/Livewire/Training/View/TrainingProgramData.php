<?php

namespace App\Livewire\Training\View;

use App\Data\AbstractData;
use App\Data\Form\Fields\Relationship;
use App\Data\Form\Fields\Select;
use App\Data\Form\Fields\Text;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseType\StrengthDefaults;
use App\Models\TrainingPlanProgram;
use App\Models\TrainingPlanProgramExercise;

class TrainingProgramData extends AbstractData implements HasForms
{
    public const DEFAULT_COLOR = 'blue';

    public const AVAILABLE_COLORS = [
        'blue' => 'Blue',
        'green' => 'Green',
        'emerald' => 'Emerald',
        'teal' => 'Teal',
        'cyan' => 'Cyan',
        'sky' => 'Sky',
        'indigo' => 'Indigo',
        'violet' => 'Violet',
        'purple' => 'Purple',
        'pink' => 'Pink',
        'rose' => 'Rose',
        'red' => 'Red',
        'orange' => 'Orange',
        'amber' => 'Amber',
        'yellow' => 'Yellow',
        'lime' => 'Lime',
    ];

    public function __construct(
        public ?int $id,
        public ?int $training_plan_id,
        public string $name,
        public string $color = self::DEFAULT_COLOR,
        public array $exercises = [],
    ) {}

    public static function fromTrainingPlanProgram(TrainingPlanProgram $program): self
    {
        $exercises = [];
        if ($program->relationLoaded('exercises')) {
            $exercises = $program->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'sort' => $exercise->pivot->sort ?? 0,
            ])->all();
        }

        return new self(
            id: $program->id,
            training_plan_id: $program->training_plan_id,
            name: $program->name ?? '',
            color: $program->extra->get('color', self::DEFAULT_COLOR),
            exercises: $exercises,
        );
    }

    public function persist(): void
    {
        if ($this->id === null) {
            $maxSort = TrainingPlanProgram::where('training_plan_id', $this->training_plan_id)->max('sort') ?? -1;
            $program = TrainingPlanProgram::create([
                'training_plan_id' => $this->training_plan_id,
                'name' => $this->name,
                'sort' => $maxSort + 1,
            ]);
            $this->id = $program->id;
        } else {
            $program = TrainingPlanProgram::findOrFail($this->id);
            $program->name = $this->name;
            $program->save();
        }

        $program->extra->set('color', $this->color);
        $program->save();

        $this->syncExercisesWithDefaults($program);
    }

    protected function syncExercisesWithDefaults(TrainingPlanProgram $program): void
    {
        $currentExerciseIds = $program->exercises()->pluck('exercises.id')->toArray();
        $newExerciseIds = collect($this->exercises)
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->pluck('id')
            ->toArray();

        $exercisesToAdd = array_diff($newExerciseIds, $currentExerciseIds);
        $exercisesToRemove = array_diff($currentExerciseIds, $newExerciseIds);

        TrainingPlanProgramExercise::where('training_plan_program_id', $program->id)
            ->whereIn('exercise_id', $exercisesToRemove)
            ->delete();

        foreach ($this->exercises as $index => $exerciseData) {
            if (empty($exerciseData['id'])) {
                continue;
            }

            $exerciseId = $exerciseData['id'];
            $sort = $exerciseData['sort'] ?? $index;

            if (in_array($exerciseId, $exercisesToAdd)) {
                $exercise = Exercise::find($exerciseId);
                if ($exercise) {
                    $defaults = StrengthDefaults::fromExercise($exercise);
                    TrainingPlanProgramExercise::create([
                        'training_plan_program_id' => $program->id,
                        'exercise_id' => $exerciseId,
                        'sort' => $sort,
                        'extra' => [
                            'oneRepMaxModifier' => $defaults->oneRepMaxModifier,
                            'startingReps' => $defaults->startingReps,
                            'timeUnderTension' => $defaults->timeUnderTension,
                            'rest' => $defaults->rest,
                        ],
                    ]);
                }
            } else {
                TrainingPlanProgramExercise::where('training_plan_program_id', $program->id)
                    ->where('exercise_id', $exerciseId)
                    ->update(['sort' => $sort]);
            }
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
            Text::make('name')
                ->label('Name')
                ->placeholder('Program name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
            Select::make('color')
                ->label('Color')
                ->options(self::AVAILABLE_COLORS)
                ->default(self::DEFAULT_COLOR),
            Relationship::make('exercises')
                ->label('Exercises')
                ->options($exerciseOptions)
                ->placeholder('Select exercise')
                ->sortable()
                ->default([]),
        ];
    }
}
