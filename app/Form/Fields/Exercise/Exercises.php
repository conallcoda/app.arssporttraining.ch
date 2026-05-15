<?php

namespace App\Form\Fields\Exercise;

use App\Form\Fields\RelationshipSelector;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use Coda\Cms\Display\DisplayFields\CompactDisplay;
use Coda\FormKit\Fields\Select;

class Exercises extends RelationshipSelector
{
    /** @var array<string, iterable<mixed>> */
    private static array $searchResultsCache = [];

    /** @var array<string, iterable<mixed>> */
    private static array $selectedRecordsCache = [];

    public static function flushRequestCaches(): void
    {
        self::$searchResultsCache = [];
        self::$selectedRecordsCache = [];
    }

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Exercises';
        $this->modalTitle = 'Select Exercises';
        $this->deferModalApply = true;
        $this->clientModal = true;
        $this->inlineSelectionDisplay = 'badges';
        $this->showInlineSchema = false;
        $this->placeholder = 'Search exercises';
        $this->sortable = true;
        $this->default = [];
        $this->selectButtonLabel = 'Select';
        $this->triggerButtonLabel = 'Edit';
        $this->triggerButtonIcon = 'pencil';
        $this->emptySelectionText = 'No exercises selected yet.';
        $this->columns([
            CompactDisplay::make('compact')
                ->source(function (mixed $record): array {
                    if (! $record instanceof Exercise) {
                        return [
                            'title' => (string) data_get($record, 'name', ''),
                            'badges' => [],
                            'meta' => [],
                        ];
                    }

                    $exercise = $record;
                    $category = $exercise->category;

                    return [
                        'title' => $exercise->name,
                        'badges' => array_values(array_filter([
                            $category ? [
                                'label' => $category->short_name ?: $category->name,
                                'color' => $category->color,
                            ] : null,
                        ])),
                        'meta' => $exercise->modifiers
                            ->map(fn ($tag) => ['label' => $tag->short_name ?: $tag->name, 'color' => 'zinc'])
                            ->values()
                            ->all(),
                    ];
                }),
        ]);
        $this->schema([
            Select::make('group')
                ->label('Group')
                ->placeholder('-')
                ->options(array_combine(range('A', 'Z'), range('A', 'Z')))
                ->variant('listbox')
                ->clearable()
                ->live(),
        ]);
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => Exercise::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
        $this->cached('exercise-selector-options');

        return $this;
    }

    public function withSearch(): static
    {
        return $this->searchable(function (string $query, array $selectedIds, array $excludedIds, array $filters, array $items, array $sort, int $offset = 0, int $limit = 40): iterable {
            $cacheKey = 'search:'.md5(json_encode([$query, $offset, $limit]));

            if (isset(self::$searchResultsCache[$cacheKey])) {
                return self::$searchResultsCache[$cacheKey];
            }

            return self::$searchResultsCache[$cacheKey] = Exercise::query()
                ->with([
                    'category:id,name,short_name,color',
                    'modifiers:id,name,short_name',
                ])
                ->when($query !== '', fn ($q) => $q->where(function ($w) use ($query) {
                    $w->where('exercises.name', 'like', "%{$query}%")
                        ->orWhereHas('category', fn ($c) => $c->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('equipment', fn ($e) => $e->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('modifiers', fn ($m) => $m->where('tags.name', 'like', "%{$query}%"));
                }))
                ->orderBy('name')
                ->offset(max(0, $offset))
                ->limit(max(1, $limit))
                ->get();
        })->selectedRecordsUsing(function (array $selectedIds): iterable {
            $selectedIdInts = collect($selectedIds)
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($selectedIdInts === []) {
                return collect();
            }

            $cacheKey = 'selected:'.implode(',', $selectedIdInts);

            if (isset(self::$selectedRecordsCache[$cacheKey])) {
                return self::$selectedRecordsCache[$cacheKey];
            }

            return self::$selectedRecordsCache[$cacheKey] = Exercise::query()
                ->with([
                    'category:id,name,short_name,color',
                    'modifiers:id,name,short_name',
                ])
                ->whereKey($selectedIdInts)
                ->get()
                ->sortBy(fn (Exercise $exercise) => array_search($exercise->id, $selectedIdInts, true))
                ->values();
        });
    }

    public function withOptionView(): static
    {
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultClientModalLists(): array
    {
        return [
            $this->exerciseSearchClientModalList(),
            $this->programSearchClientModalList(),
            $this->selectedExercisesClientModalList(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exerciseSearchClientModalList(): array
    {
        return [
            'key' => 'exercises',
            'label' => 'Exercises',
            'rows' => 'resultRows',
            'loader' => 'default',
            'searchable' => true,
            'selectedState' => 'isSelected',
            'rowAction' => null,
            'emptyText' => 'No matches found.',
            'button' => [
                'defaultLabel' => $this->selectButtonLabel,
                'selectedLabel' => 'Selected',
                'defaultColor' => 'zinc',
                'selectedColor' => 'blue',
                'action' => 'toggleRecord',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function programSearchClientModalList(): array
    {
        return [
            'key' => 'programs',
            'label' => 'Programs',
            'rows' => 'resultRows',
            'loader' => 'loadExerciseSelectorPrograms',
            'searchable' => true,
            'selectedState' => 'never',
            'rowAction' => null,
            'emptyText' => 'No programs found.',
            'button' => [
                'defaultLabel' => 'Import',
                'selectedLabel' => 'Import',
                'defaultColor' => 'blue',
                'selectedColor' => 'blue',
                'visibleField' => 'selector_program_has_exercises',
                'action' => [
                    'type' => 'wire',
                    'name' => 'importExercisesFromProgramSelector',
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function selectedExercisesClientModalList(): array
    {
        return [
            'key' => 'selected',
            'label' => 'Selected',
            'rows' => 'selectedRows',
            'sortable' => $this->sortable,
            'sortKey' => $this->sortable ? 'rowSortKey' : null,
            'loader' => null,
            'searchable' => false,
            'selectedState' => 'always',
            'rowAction' => null,
            'emptyText' => $this->emptySelectionText,
            'badge' => ['mode' => 'selected-count'],
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
        ];
    }
}
