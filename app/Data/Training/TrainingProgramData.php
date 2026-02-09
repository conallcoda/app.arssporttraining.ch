<?php

namespace App\Data\Training;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Training\Program\Color;
use App\Form\Fields\Training\Program\ProgramName;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlanProgram;
use App\Models\TrainingPlanProgramExercise;

class TrainingProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public ?int $training_plan_id,
        public string $name,
        public string $color = Color::DEFAULT_COLOR,
        public array $exercises = [],
    ) {}

    public static function fromTrainingPlanProgram(TrainingPlanProgram $program): self
    {
        $program->loadMissing(['exercises' => fn ($q) => $q->orderByPivot('sort')]);

        $exercises = $program->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'sort' => $exercise->pivot->sort ?? 0,
        ])->all();

        return new self(
            id: $program->id,
            training_plan_id: $program->training_plan_id,
            name: $program->name ?? '',
            color: $program->config->get('color', Color::DEFAULT_COLOR),
            exercises: $exercises,
        );
    }

    public function persist(): void
    {
        $values = [
            'training_plan_id' => $this->training_plan_id,
            'name' => $this->name,
        ];

        if ($this->id === null) {
            $values['sort'] = (TrainingPlanProgram::where('training_plan_id', $this->training_plan_id)->max('sort') ?? -1) + 1;
        }

        $program = TrainingPlanProgram::updateOrCreate(['id' => $this->id], $values);

        $this->id = $program->id;

        $program->config->set('color', $this->color);
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
                    $defaults = $exercise->config?->strength_automatic;
                    TrainingPlanProgramExercise::create([
                        'training_plan_program_id' => $program->id,
                        'exercise_id' => $exerciseId,
                        'sort' => $sort,
                        'config' => [
                            'oneRepMaxModifier' => $defaults?->oneRepMaxModifier ?? 100,
                            'startingReps' => $defaults?->startingReps ?? 12,
                            'tempo' => $defaults?->tempo ?? '3010',
                            'rest' => $defaults?->rest ?? 30,
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

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                ProgramName::make('name'),
                Color::make('color'),
                Exercises::make('exercises')->withOptions(),
            ]);
    }
}
