<?php

namespace App\Data\Training;

use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Training\Program\ExerciseCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExerciseProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name = '',
        public ?int $exercise_category_id = null,
        public ?string $exerciseCategoryName = null,
        public ?string $exerciseCategoryColor = null,
        public array $exercises = [],
        public ?Carbon $updatedAt = null,
        public int $sort = 0,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = $payloads[0] ?? $payloads;

        if ($data instanceof ExerciseProgram) {
            return self::fromModel($data);
        }

        return new static(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            exercise_category_id: isset($data['exercise_category_id']) ? (int) $data['exercise_category_id'] : null,
            exercises: $data['exercises'] ?? [],
            sort: (int) ($data['sort'] ?? 0),
        );
    }

    public static function fromModel(ExerciseProgram $program): static
    {
        $program->loadMissing([
            'exercises' => fn ($q) => $q->orderByPivot('sort'),
            'exerciseCategory',
        ]);

        $exercises = $program->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'sort' => $exercise->pivot->sort ?? 0,
        ])->all();

        return new static(
            id: $program->id,
            name: $program->name,
            exercise_category_id: $program->exercise_category_id,
            exerciseCategoryName: $program->exerciseCategory?->name,
            exerciseCategoryColor: $program->exerciseCategory?->color,
            exercises: $exercises,
            updatedAt: $program->updated_at,
            sort: $program->sort,
        );
    }

    public function persist(): void
    {
        $program = ExerciseProgram::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'exercise_category_id' => $this->exercise_category_id,
                'sort' => $this->sort,
            ]
        );

        $this->id = $program->id;

        $this->syncExercises($program);
    }

    protected function syncExercises(ExerciseProgram $program): void
    {
        $currentExerciseIds = $program->exercises()->pluck('exercises.id')->toArray();
        $newExerciseIds = collect($this->exercises)
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->pluck('id')
            ->toArray();

        $exercisesToRemove = array_diff($currentExerciseIds, $newExerciseIds);

        ExerciseProgramExercise::where('exercise_program_id', $program->id)
            ->whereIn('exercise_id', $exercisesToRemove)
            ->delete();

        foreach ($this->exercises as $index => $exerciseData) {
            if (empty($exerciseData['id'])) {
                continue;
            }

            $exerciseId = $exerciseData['id'];
            $sort = $exerciseData['sort'] ?? $index;

            if (in_array($exerciseId, array_diff($newExerciseIds, $currentExerciseIds))) {
                ExerciseProgramExercise::create([
                    'exercise_program_id' => $program->id,
                    'exercise_id' => $exerciseId,
                    'sort' => $sort,
                ]);
            } else {
                ExerciseProgramExercise::where('exercise_program_id', $program->id)
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
                ExerciseCategory::make('exercise_category_id')->withOptions(),
                Exercises::make('exercises')->withOptions(),
            ]);
    }
}
