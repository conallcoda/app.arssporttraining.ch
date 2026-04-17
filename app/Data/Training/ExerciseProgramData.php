<?php

namespace App\Data\Training;

use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Owner;
use App\Form\Fields\Training\Program\ExerciseCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Training\TrainingSessionRebuildService;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Fields\Tags;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields\RadioSegmented;
use Coda\FormKit\Form;

class ExerciseProgramData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name = '',
        public string $type = 'program',
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
            type: $data['type'] ?? ExerciseProgramTypeEnum::Program->value,
            exercise_category_id: isset($data['exercise_category_id']) ? (int) $data['exercise_category_id'] : null,
            exercises: $data['exercises'] ?? [],
            sort: (int) ($data['sort'] ?? 0),
            owner_id: isset($data['owner_id']) ? (int) $data['owner_id'] : null,
        );
    }

    public static function fromModel(ExerciseProgram $program): static
    {
        $program->load([
            'exercises' => fn ($q) => $q->wherePivot('type', 'main')->orderByPivot('sort'),
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
            type: $program->type?->value ?? ExerciseProgramTypeEnum::Program->value,
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
        );
    }

    public function persist(): void
    {
        $program = ExerciseProgram::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'type' => $this->type,
                'exercise_category_id' => $this->exercise_category_id,
                'sort' => $this->sort,
                'owner_id' => $this->owner_id,
            ]
        );

        $this->id = $program->id;

        $this->syncExercises($program);

        $program->tags()->sync($this->internalTags);

        app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForExerciseProgram($program->id);
    }

    protected function syncExercises(ExerciseProgram $program): void
    {
        $currentExerciseIds = $program->exercises()
            ->wherePivot('type', 'main')
            ->pluck('exercises.id')
            ->toArray();
        $newExerciseIds = collect($this->exercises)
            ->filter(fn ($exercise) => ! empty($exercise['id']))
            ->pluck('id')
            ->toArray();

        $exercisesToRemove = array_diff($currentExerciseIds, $newExerciseIds);

        ExerciseProgramExercise::where('exercise_program_id', $program->id)
            ->where('type', 'main')
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
                    'type' => 'main',
                ]);
            } else {
                ExerciseProgramExercise::where('exercise_program_id', $program->id)
                    ->where('exercise_id', $exerciseId)
                    ->where('type', 'main')
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
                RadioSegmented::make('type')
                    ->label(__('Type'))
                    ->options(ExerciseProgramTypeEnum::options())
                    ->default(ExerciseProgramTypeEnum::Program->value)
                    ->required()
                    ->live(),
                ExerciseCategory::make('exercise_category_id')->withOptions(),
                Exercises::make('exercises')->withOptions()->withSearch()->withOptionView(),
                Tags::make('internalTags', 'program_internal')->label('Tags')->withOptions()->create(),
            ]);
    }
}
