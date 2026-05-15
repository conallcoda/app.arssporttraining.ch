<?php

namespace App\Form\Fields\Exercise;

use App\Models\Exercise\Exercise;
use Coda\Cms\Display\DisplayFields\CompactDisplay;
use Coda\FormKit\Fields\RelationshipSelector;
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
        $this->placeholder = 'Search exercises';
        $this->sortable = true;
        $this->default = [];
        $this->selectButtonLabel = 'Select';
        $this->emptySelectionText = 'No exercises selected yet.';
        $this->columns([
            CompactDisplay::make('compact')
                ->source(function (Exercise $exercise): array {
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
        return $this->searchable(function (string $query, array $selectedIds, array $excludedIds, array $filters, array $items, array $sort): iterable {
            $cacheKey = 'search:'.md5(json_encode([$query]));

            if (isset(self::$searchResultsCache[$cacheKey])) {
                return self::$searchResultsCache[$cacheKey];
            }

            return self::$searchResultsCache[$cacheKey] = Exercise::query()
                ->with(['category', 'equipment', 'modifiers'])
                ->when($query !== '', fn ($q) => $q->where(function ($w) use ($query) {
                    $w->where('exercises.name', 'like', "%{$query}%")
                        ->orWhereHas('category', fn ($c) => $c->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('equipment', fn ($e) => $e->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('modifiers', fn ($m) => $m->where('tags.name', 'like', "%{$query}%"));
                }))
                ->orderBy('name')
                ->limit(40)
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
                ->with(['category', 'equipment', 'modifiers'])
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
}
