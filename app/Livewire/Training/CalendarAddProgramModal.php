<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Training\Program\ExerciseCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Livewire\Concerns\InteractsWithExerciseSelectorPrograms;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use Coda\Cms\Display\DisplayFields\CompactDisplay;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CalendarAddProgramModal extends Component
{
    use InteractsWithFormData;
    use InteractsWithExerciseSelectorPrograms;

    private const SELECTOR_FIELD = 'calendar_program_selection';

    public int $groupId;

    public ?int $userId = null;

    public array $data = [
        self::SELECTOR_FIELD => [],
    ];

    #[On('trigger-add-content')]
    public function open(): void
    {
        if ($this->groupId <= 0) {
            return;
        }

        $this->resetSelectorState();

        $this->dispatch('relationship-selector-open', fieldName: self::SELECTOR_FIELD);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $field = Exercises::make(self::SELECTOR_FIELD)
            ->label('Add Program')
            ->modalTitle('Add Program')
            ->triggerButtonLabel('Add Program')
            ->clientModalSaveAction('createCalendarProgramFromSelector')
            ->clientModalInitialListKey('programs')
            ->clientModalStateDefaults($this->selectorModalStateDefaults())
            ->clientModalStateFields([
                [
                    'field' => ProgramName::make('name')->label('Program Name'),
                    'listKey' => 'selected',
                ],
                [
                    'field' => ExerciseCategory::make('exercise_category_id')->withOptions(),
                    'listKey' => 'selected',
                ],
            ])
            ->clientModalLists([
                [
                    'key' => 'programs',
                    'label' => 'Programs',
                    'rows' => 'resultRows',
                    'loader' => 'loadExerciseSelectorPrograms',
                    'searchable' => true,
                    'searchPlaceholder' => 'Search programs...',
                    'selectedState' => 'never',
                    'rowAction' => null,
                    'emptyText' => 'No programs found.',
                    'button' => [
                        'defaultLabel' => 'Add',
                        'selectedLabel' => 'Add',
                        'defaultColor' => 'blue',
                        'selectedColor' => 'blue',
                        'action' => [
                            'type' => 'wire',
                            'name' => 'addCalendarProgramFromSelector',
                            'passSelectedItems' => false,
                        ],
                    ],
                    'columns' => [
                        CompactDisplay::make('compact')
                            ->source(function (mixed $record): array {
                                if (! $record instanceof ExerciseProgram) {
                                    return [
                                        'title' => (string) data_get($record, 'name', ''),
                                        'badges' => [],
                                        'meta' => [],
                                    ];
                                }

                                $program = $record;

                                return [
                                    'title' => $program->name,
                                    'badges' => $program->selector_program_badges ?? [[
                                        'label' => ($program->type instanceof ExerciseProgramTypeEnum ? $program->type : ExerciseProgramTypeEnum::tryFrom((string) $program->type))?->label() ?? 'Program',
                                        'color' => 'blue',
                                    ]],
                                    'meta' => $program->selector_program_exercises ?? [],
                                ];
                            }),
                    ],
                ],
                [
                    'key' => 'exercises',
                    'label' => 'Exercises',
                    'rows' => 'resultRows',
                    'loader' => 'default',
                    'searchable' => true,
                    'searchPlaceholder' => 'Search exercises...',
                    'selectedState' => 'isSelected',
                    'rowAction' => null,
                    'emptyText' => 'No matches found.',
                    'button' => [
                        'defaultLabel' => 'Select',
                        'selectedLabel' => 'Selected',
                        'defaultColor' => 'zinc',
                        'selectedColor' => 'blue',
                        'action' => 'toggleRecord',
                    ],
                ],
                [
                    'key' => 'selected',
                    'label' => 'Create',
                    'rows' => 'selectedRows',
                    'sortable' => true,
                    'sortKey' => 'rowSortKey',
                    'loader' => null,
                    'searchable' => false,
                    'selectedState' => 'always',
                    'rowAction' => null,
                    'emptyText' => 'No exercises selected yet.',
                    'badge' => ['mode' => 'selected-count'],
                    'saveButton' => [
                        'visible' => true,
                        'label' => 'Create',
                    ],
                    'button' => [
                        'defaultLabel' => 'Remove',
                        'selectedLabel' => 'Remove',
                        'defaultColor' => 'red',
                        'selectedColor' => 'red',
                        'icon' => 'trash-2',
                        'iconOnly' => true,
                        'action' => 'toggleRecord',
                    ],
                    'itemFields' => [
                        [
                            'key' => 'group',
                            'label' => 'Group',
                            'type' => 'select',
                            'placeholder' => '-',
                            'clearable' => true,
                            'options' => array_combine(range('A', 'Z'), range('A', 'Z')),
                        ],
                    ],
                ],
            ])
            ->withSearch()
            ->withOptionView();

        return Form::make()->fieldset('Add Program', [$field], prefix: 'data');
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    public function addCalendarProgramFromSelector(
        string $fieldName,
        string $listKey,
        array $programRecord,
    ): array {
        $programId = isset($programRecord['key']) ? (int) $programRecord['key'] : 0;

        if ($programId <= 0) {
            return [];
        }

        $program = ExerciseProgram::findOrFail($programId);

        if ($program->type !== ExerciseProgramTypeEnum::Program) {
            Flux::toast(text: __('Only regular programs can be added here.'), variant: 'danger');

            return [];
        }

        TrainingProgram::importProgram($program, $this->groupId);
        $this->resetSelectorState();

        $this->dispatch('programs-changed');
        Flux::toast(text: __('Program added'), variant: 'success');

        return [
            'selectedItems' => [],
            'modalState' => $this->selectorModalStateDefaults(),
            'activeListKey' => 'programs',
            'closeModal' => true,
        ];
    }

    public function createCalendarProgramFromSelector(
        string $fieldName,
        array $items = [],
        array $modalState = [],
    ): void {
        $name = trim((string) ($modalState['name'] ?? ''));
        $categoryId = isset($modalState['exercise_category_id']) && $modalState['exercise_category_id'] !== ''
            ? (int) $modalState['exercise_category_id']
            : null;
        $exerciseRows = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (int) $item['id'],
                'sort' => (int) ($item['sort'] ?? 0),
                'group' => $item['group'] ?? null,
            ])
            ->values()
            ->all();

        if ($name === '') {
            Flux::toast(text: __('Enter a program name.'), variant: 'danger');

            return;
        }

        if ($categoryId === null || ! Tag::query()->forScope('exercise_category')->whereNull('parent_id')->whereKey($categoryId)->exists()) {
            Flux::toast(text: __('Select a category.'), variant: 'danger');

            return;
        }

        if ($exerciseRows === []) {
            Flux::toast(text: __('Select at least one exercise.'), variant: 'danger');

            return;
        }

        DB::transaction(function () use ($name, $categoryId, $exerciseRows): void {
            $programData = new ExerciseProgramData(
                id: null,
                name: $name,
                type: ExerciseProgramTypeEnum::Program->value,
                exercise_category_id: $categoryId,
                exercises: $exerciseRows,
            );

            $programData->persist();

            $program = ExerciseProgram::findOrFail($programData->id);
            $maxSort = TrainingProgram::where('group_id', $this->groupId)->max('sort') ?? -1;

            $trainingProgram = TrainingProgram::create([
                'group_id' => $this->groupId,
                'exercise_program_id' => $program->id,
                'sort' => $maxSort + 1,
                'status' => null,
            ]);

            $program->update([
                'parent_type' => TrainingProgram::class,
                'parent_id' => $trainingProgram->id,
            ]);
        });

        $this->resetSelectorState();

        $this->dispatch('programs-changed');
        Flux::toast(text: __('Program created'), variant: 'success');
        Flux::modal($this->relationshipSelectorModalName($fieldName))->close();
    }

    protected function exerciseSelectorImportProgramType(string $fieldName): ExerciseProgramTypeEnum
    {
        return ExerciseProgramTypeEnum::Program;
    }

    /**
     * @return array<int, string>
     */
    protected function exerciseSelectorImportProgramTypes(string $fieldName): array
    {
        return [ExerciseProgramTypeEnum::Program->value];
    }

    protected function exerciseSelectorCurrentProgramId(string $fieldName): ?int
    {
        return null;
    }

    protected function resetSelectorState(): void
    {
        Exercises::flushRequestCaches();
        $this->data[self::SELECTOR_FIELD] = [];
        unset(
            $this->relationshipSelectorDraftItems[self::SELECTOR_FIELD],
            $this->relationshipSelectorSearch[self::SELECTOR_FIELD],
            $this->relationshipSelectorFilters[self::SELECTOR_FIELD],
            $this->relationshipSelectorTab[self::SELECTOR_FIELD],
            $this->formConfig,
            $this->fieldsets,
        );
    }

    /**
     * @return array{name: string, exercise_category_id: string}
     */
    protected function selectorModalStateDefaults(): array
    {
        return [
            'name' => '',
            'exercise_category_id' => '',
        ];
    }

    public function render(): View
    {
        return view('livewire.training.calendar-add-program-modal');
    }
}
