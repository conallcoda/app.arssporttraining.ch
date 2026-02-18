<?php

namespace App\Data\Training;

use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Training\Program\ProgramCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Models\Exercise\Exercise;
use App\Models\TrainingPlanProgram;
use App\Models\TrainingPlanProgramExercise;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class TrainingProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public ?int $training_plan_id,
        public string $name,
        public ?int $program_category_id = null,
        public ?string $categoryName = null,
        public ?string $categoryColor = null,
        public array $exercises = [],
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromTrainingPlanProgram(TrainingPlanProgram $program): self
    {
        $program->loadMissing([
            'exercises' => fn ($q) => $q->orderByPivot('sort'),
            'programCategory',
        ]);

        $exercises = $program->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'sort' => $exercise->pivot->sort ?? 0,
        ])->all();

        return new self(
            id: $program->id,
            training_plan_id: $program->training_plan_id,
            name: $program->name ?? '',
            program_category_id: $program->program_category_id,
            categoryName: $program->programCategory?->name,
            categoryColor: $program->programCategory?->color,
            exercises: $exercises,
            updatedAt: $program->updated_at,
        );
    }

    public function persist(): void
    {
        $values = [
            'training_plan_id' => $this->training_plan_id,
            'name' => $this->name,
            'program_category_id' => $this->program_category_id,
        ];

        if ($this->id === null) {
            $values['sort'] = (TrainingPlanProgram::where('training_plan_id', $this->training_plan_id)->max('sort') ?? -1) + 1;
        }

        $program = TrainingPlanProgram::updateOrCreate(['id' => $this->id], $values);

        $this->id = $program->id;

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
                    $configArray = json_decode($exercise->getRawOriginal('config') ?? '{}', true) ?: [];
                    TrainingPlanProgramExercise::create([
                        'training_plan_program_id' => $program->id,
                        'exercise_id' => $exerciseId,
                        'sort' => $sort,
                        'config' => [
                            'oneRepMaxModifier' => $configArray['weight']['oneRepMaxModifier'] ?? 100,
                            'startingReps' => $configArray['reps']['default'] ?? 12,
                            'tempo' => $configArray['tempo']['default'] ?? '3010',
                            'rest' => $configArray['rest']['default'] ?? 30,
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
                ProgramCategory::make('program_category_id')->withOptions(),
                Exercises::make('exercises')->withOptions(),
            ]);
    }
}
