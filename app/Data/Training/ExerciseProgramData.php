<?php

namespace App\Data\Training;

use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Owner;
use App\Form\Fields\Training\Program\ExerciseCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Form\Fields\Training\Program\SelectProgram;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields;
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
        public array $internalTags = [],
        public ?Carbon $updatedAt = null,
        public int $sort = 0,
        public ?int $owner_id = null,
        public ?string $ownerName = null,
        public ?string $ownerColor = null,
        public ?int $warm_up_program_id = null,
        public ?int $warm_down_program_id = null,
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
            owner_id: isset($data['owner_id']) ? (int) $data['owner_id'] : null,
            warm_up_program_id: isset($data['warm_up_program_id']) ? (int) $data['warm_up_program_id'] : null,
            warm_down_program_id: isset($data['warm_down_program_id']) ? (int) $data['warm_down_program_id'] : null,
        );
    }

    public static function fromModel(ExerciseProgram $program): static
    {
        $program->loadMissing([
            'exercises' => fn ($q) => $q->orderByPivot('sort'),
            'exerciseCategory',
            'internalTags',
            'owner',
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
            internalTags: $program->internalTags->pluck('id')->all(),
            updatedAt: $program->updated_at,
            sort: $program->sort,
            owner_id: $program->owner_id ?? 0,
            ownerName: $program->owner?->name,
            ownerColor: $program->owner?->color,
            warm_up_program_id: $program->warm_up_program_id,
            warm_down_program_id: $program->warm_down_program_id,
        );
    }

    public function persist(): void
    {
        $program = ExerciseProgram::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'exercise_category_id' => $this->exercise_category_id,
                'warm_up_program_id' => $this->warm_up_program_id,
                'warm_down_program_id' => $this->warm_down_program_id,
                'sort' => $this->sort,
                'owner_id' => $this->owner_id,
            ]
        );

        $this->id = $program->id;

        $this->syncExercises($program);

        $program->tags()->sync($this->internalTags);
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

    public static function getForm(?int $excludeId = null): Form
    {
        return Form::make()
            ->fieldset('General', [
                Owner::make('owner_id')->withOptions()->allowUnassigned(),
                ProgramName::make('name'),
                ExerciseCategory::make('exercise_category_id')->withOptions(),
                Exercises::make('exercises')->withOptions(),
                SelectProgram::make('warm_up_program_id')->label('Warm Up')->withOptions(fn ($q) => $q->whereNull('parent_type')->whereNull('parent_id')->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))),
                SelectProgram::make('warm_down_program_id')->label('Warm Down')->withOptions(fn ($q) => $q->whereNull('parent_type')->whereNull('parent_id')->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))),
                Fields\Tags::make('internalTags', 'program_internal')->label('Tags')->withOptions()->create(),
            ]);
    }
}
