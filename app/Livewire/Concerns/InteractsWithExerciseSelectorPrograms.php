<?php

namespace App\Livewire\Concerns;

use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use Coda\FormKit\Field;
use Coda\FormKit\Fields\RelationshipSelector;

trait InteractsWithExerciseSelectorPrograms
{
    public function loadExerciseSelectorPrograms(
        string $fieldName,
        string $listKey,
        string $query = '',
        int $offset = 0,
        int $limit = 40,
        array $selectedItems = [],
    ): array {
        $field = $this->findField($fieldName);

        if (! $field instanceof Exercises) {
            return ['records' => [], 'nextOffset' => 0, 'hasMore' => false];
        }

        $fetchLimit = max(1, $limit) + 1;
        $programs = ExerciseProgram::query()
            ->with([
                'exerciseCategory:id,name,short_name,color',
                'exercises' => fn ($query) => $query->orderByPivot('type')->orderByPivot('sort')->orderByPivot('id'),
            ])
            ->whereNull('exercise_programs.owner_id')
            ->whereNull('exercise_programs.parent_id')
            ->whereNull('exercise_programs.parent_type')
            ->where('exercise_programs.type', $this->exerciseSelectorImportProgramType($fieldName)->value)
            ->when($this->exerciseSelectorCurrentProgramId($fieldName), fn ($programs, $id) => $programs->where('exercise_programs.id', '!=', $id))
            ->when($query !== '', fn ($programs) => $programs->where('exercise_programs.name', 'like', '%'.$query.'%'))
            ->orderBy('exercise_programs.name')
            ->offset(max(0, $offset))
            ->limit($fetchLimit)
            ->get()
            ->each(fn (ExerciseProgram $program) => $this->decorateExerciseSelectorProgramRecord($program, $fieldName));

        $hasMore = $programs->count() > max(1, $limit);
        $pageRecords = $programs->take(max(1, $limit))->values();

        return [
            'records' => $pageRecords
                ->map(fn (ExerciseProgram $program) => $field->serializeRecordForClientModal($program))
                ->values()
                ->all(),
            'nextOffset' => max(0, $offset) + $pageRecords->count(),
            'hasMore' => $hasMore,
        ];
    }

    public function importExercisesFromProgramSelector(
        string $fieldName,
        string $listKey,
        array $programRecord,
        array $selectedItems = [],
    ): array {
        $field = $this->findField($fieldName);

        if (! $field instanceof Exercises) {
            return [];
        }

        $programId = isset($programRecord['key']) ? (int) $programRecord['key'] : 0;

        if ($programId <= 0) {
            return [];
        }

        $sourceProgram = ExerciseProgram::query()
            ->with([
                'exercises' => fn ($query) => $query->orderByPivot('type')->orderByPivot('sort')->orderByPivot('id'),
            ])
            ->findOrFail($programId);

        $items = $selectedItems !== []
            ? $this->cloneRelationshipSelectorItems($selectedItems)
            : $this->cloneRelationshipSelectorItems($this->data[$fieldName] ?? []);

        $selectedIds = collect($items)
            ->pluck($field->valueAttribute)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();

        $sourceRows = $this->exerciseSelectorSourceRows($sourceProgram, $fieldName);

        foreach ($sourceRows as $exercise) {
            $exerciseId = (string) $exercise->id;

            if (in_array($exerciseId, $selectedIds, true)) {
                continue;
            }

            $item = array_merge(
                Field::buildDefaults($field->schema),
                [
                    $field->valueAttribute => $exercise->id,
                    '_key' => uniqid('item_', true),
                    'source_program_id' => $sourceProgram->id,
                    'source_program_exercise_id' => (int) ($exercise->pivot->id ?? 0),
                ],
            );

            if (! empty($exercise->pivot?->group)) {
                $item['group'] = $exercise->pivot->group;
            }

            $items[] = $item;
            $selectedIds[] = $exerciseId;
        }

        if ($field->sortable) {
            foreach ($items as $index => $item) {
                $items[$index]['sort'] = $index;
            }
        }

        return [
            'activeListKey' => $sourceRows->isNotEmpty() ? 'selected' : $listKey,
            'selectedItems' => $this->buildRelationshipSelectorClientSelectedItems($field, $items, $selectedIds, []),
            'refreshLists' => ['exercises'],
        ];
    }

    protected function exerciseSelectorImportProgramType(string $fieldName): ExerciseProgramTypeEnum
    {
        return ExerciseProgramTypeEnum::Program;
    }

    protected function exerciseSelectorCurrentProgramId(string $fieldName): ?int
    {
        $id = data_get($this, 'data.id');

        return is_numeric($id) ? (int) $id : null;
    }

    protected function exerciseSelectorTargetSection(string $fieldName): string
    {
        return 'main';
    }

    protected function exerciseSelectorSourceSection(ExerciseProgram $sourceProgram, string $fieldName): string
    {
        return $this->normalizeExerciseSelectorProgramType($sourceProgram->type) === ExerciseProgramTypeEnum::Program
            ? $this->exerciseSelectorTargetSection($fieldName)
            : 'main';
    }

    protected function exerciseSelectorSourceRows(ExerciseProgram $sourceProgram, string $fieldName)
    {
        $sourceSection = $this->exerciseSelectorSourceSection($sourceProgram, $fieldName);

        return $sourceProgram->exercises
            ->filter(fn (Exercise $exercise) => ($exercise->pivot->type ?? 'main') === $sourceSection)
            ->sortBy(fn (Exercise $exercise) => [$exercise->pivot->sort ?? 0, $exercise->pivot->id ?? 0])
            ->values();
    }

    protected function decorateExerciseSelectorProgramRecord(ExerciseProgram $program, string $fieldName): void
    {
        $sourceRows = $this->exerciseSelectorSourceRows($program, $fieldName);
        $type = $this->normalizeExerciseSelectorProgramType($program->type);
        $category = $program->exerciseCategory;

        $badges = array_values(array_filter([
            $type ? [
                'label' => $type->label(),
                'color' => match ($type) {
                    ExerciseProgramTypeEnum::Program => 'blue',
                    ExerciseProgramTypeEnum::WarmUp => 'amber',
                    ExerciseProgramTypeEnum::WarmDown => 'emerald',
                },
            ] : null,
            $category ? [
                'label' => $category->short_name ?: $category->name,
                'color' => $category->color,
            ] : null,
        ]));

        $exerciseBadges = $sourceRows
            ->take(6)
            ->map(fn (Exercise $exercise) => ['label' => $exercise->name, 'color' => 'zinc'])
            ->values()
            ->all();

        $remainingCount = $sourceRows->count() - count($exerciseBadges);

        if ($remainingCount > 0) {
            $exerciseBadges[] = ['label' => '+'.$remainingCount.' more', 'color' => 'zinc'];
        }

        $program->setAttribute('selector_program_exercise_count', $sourceRows->count());
        $program->setAttribute('selector_program_has_exercises', $sourceRows->isNotEmpty());
        $program->setAttribute('selector_program_badges', $badges);
        $program->setAttribute('selector_program_exercises', $exerciseBadges);
    }

    protected function normalizeExerciseSelectorProgramType(ExerciseProgramTypeEnum|string|null $type): ?ExerciseProgramTypeEnum
    {
        if ($type instanceof ExerciseProgramTypeEnum) {
            return $type;
        }

        return is_string($type) ? ExerciseProgramTypeEnum::tryFrom($type) : null;
    }
}
