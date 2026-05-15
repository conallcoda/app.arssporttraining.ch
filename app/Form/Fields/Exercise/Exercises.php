<?php

namespace App\Form\Fields\Exercise;

use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Coda\FormKit\Fields\RelationshipSelector;
use Coda\FormKit\Fields\Select;

class Exercises extends RelationshipSelector
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Exercises';
        $this->placeholder = 'Search exercises';
        $this->sortable = true;
        $this->default = [];
        $this->selectButtonLabel = 'Select';
        $this->emptySelectionText = 'No exercises selected yet.';
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

        return $this;
    }

    public function withSearch(): static
    {
        return $this->filters([
            Select::make('category_id')
                ->label('Category')
                ->placeholder('All categories')
                ->optionsUsing(fn () => Tag::query()
                    ->forScope('exercise_category')
                    ->whereNull('parent_id')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->clearable()
                ->live(),
        ])->searchable(function (string $query, array $selectedIds, array $excludedIds, array $filters): iterable {
            $base = fn () => Exercise::query()->with(['category', 'equipment', 'modifiers']);

            $results = $base()
                ->when(($filters['category_id'] ?? null), fn ($q, $categoryId) => $q->where('exercises.category_id', $categoryId))
                ->when($query !== '', fn ($q) => $q->where(function ($w) use ($query) {
                    $w->where('exercises.name', 'like', "%{$query}%")
                        ->orWhereHas('category', fn ($c) => $c->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('equipment', fn ($e) => $e->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('modifiers', fn ($m) => $m->where('tags.name', 'like', "%{$query}%"));
                }))
                ->orderBy('name')
                ->limit(40)
                ->get();

            $selectedIdInts = collect($selectedIds)
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($selectedIdInts->isNotEmpty()) {
                $selectedRecords = $base()
                    ->whereKey($selectedIdInts->all())
                    ->get()
                    ->keyBy('id');

                foreach (array_reverse($selectedIdInts->all()) as $selectedId) {
                    if (! $results->contains('id', $selectedId) && $selectedRecords->has($selectedId)) {
                        $results->prepend($selectedRecords->get($selectedId));
                    }
                }
            }

            return $results;
        })->selectedRecordsUsing(function (array $selectedIds): iterable {
            $selectedIdInts = collect($selectedIds)
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($selectedIdInts === []) {
                return collect();
            }

            return Exercise::query()
                ->with(['category', 'equipment', 'modifiers'])
                ->whereKey($selectedIdInts)
                ->get()
                ->sortBy(fn (Exercise $exercise) => array_search($exercise->id, $selectedIdInts, true))
                ->values();
        });
    }

    public function withOptionView(): static
    {
        return $this
            ->optionView('training.exercise-option')
            ->selectionView('training.exercise-option');
    }
}
