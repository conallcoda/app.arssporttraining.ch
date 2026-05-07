<?php

namespace App\Form\Fields\Exercise;

use App\Models\Exercise\Exercise;
use Coda\FormKit\Fields\Relationship;

class Exercises extends Relationship
{
    public bool $groupable = false;

    /** @var array<string, string> */
    public array $groupOptions = [];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Exercises';
        $this->placeholder = 'Select exercise';
        $this->sortable = true;
        $this->default = [];
    }

    public function groupable(): static
    {
        $this->groupable = true;
        $this->groupOptions = array_combine(range('A', 'Z'), range('A', 'Z'));

        return $this;
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
        return $this->searchable(function (string $query, mixed $currentValue, array $excludedIds): iterable {
            $base = fn () => Exercise::query()->with(['category', 'equipment', 'modifiers']);

            if ($query === '') {
                if ($currentValue === null || $currentValue === '') {
                    return collect();
                }

                return $base()
                    ->whereKey($currentValue)
                    ->get();
            }

            $results = $base()
                ->when($excludedIds !== [], fn ($q) => $q->whereNotIn('exercises.id', $excludedIds))
                ->when($query !== '', fn ($q) => $q->where(function ($w) use ($query) {
                    $w->where('exercises.name', 'like', "%{$query}%")
                        ->orWhereHas('category', fn ($c) => $c->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('equipment', fn ($e) => $e->where('tags.name', 'like', "%{$query}%"))
                        ->orWhereHas('modifiers', fn ($m) => $m->where('tags.name', 'like', "%{$query}%"));
                }))
                ->orderBy('name')
                ->limit(30)
                ->get();

            if ($currentValue !== null && $currentValue !== '' && ! $results->contains('id', (int) $currentValue)) {
                $selected = $base()->whereKey($currentValue)->first();

                if ($selected !== null) {
                    $results->prepend($selected);
                }
            }

            return $results;
        });
    }

    public function withOptionView(): static
    {
        return $this->optionView('training.exercise-option');
    }
}
