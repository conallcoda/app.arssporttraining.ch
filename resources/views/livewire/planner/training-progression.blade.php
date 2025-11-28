<?php

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use function Livewire\Volt\{state, computed, on};

state([
    'block' => null,
    'activeCategory' => null,
]);

$defaultWeight = 50;
$defaultSets = '14-14-12-12';

$categories = computed(fn() => TrainingSessionCategory::all());

$categoriesById = computed(fn() => $this->categories->keyBy('id'));

$parseSets = function (): array {
    return array_map('intval', explode('-', '14-14-12-12'));
};

$getColorValue = function (?string $colorName, int $shade = 500): string {
    if (!$colorName) {
        return '#000000';
    }
    return ColorPicker::getColorValue($colorName, $shade);
};

$uniqueCategoriesInBlock = computed(function () {
    if (!$this->block) {
        return collect();
    }

    $categoryIds = [];

    foreach ($this->block->getChildren() as $week) {
        if ($week->isLinked()) {
            continue;
        }
        foreach ($week->getChildren() as $session) {
            if ($session->isLinked()) {
                continue;
            }
            $categoryId = $session->getData()->category;
            if ($categoryId && !in_array($categoryId, $categoryIds)) {
                $categoryIds[] = $categoryId;
            }
        }
    }

    return $this->categories->whereIn('id', $categoryIds);
});

$sourceSessionsForCategory = computed(function () {
    if (!$this->block || !$this->activeCategory) {
        return [];
    }

    $sessions = [];
    $firstWeek = collect($this->block->getChildren())->first(fn($week) => !$week->isLinked());

    if ($firstWeek) {
        foreach ($firstWeek->getChildren() as $session) {
            if ($session->isLinked()) {
                continue;
            }
            if ($session->getData()->category === $this->activeCategory) {
                $sessions[] = [
                    'uuid' => $session->uuid,
                    'name' => $session->getData()->name,
                    'exercises' => $session->getData()->exercises ?? [],
                ];
            }
        }
    }

    return $sessions;
});

$exercisesForCategory = computed(function () {
    if (!$this->block || !$this->activeCategory) {
        return collect();
    }

    $exerciseIds = [];
    $exerciseSessionMap = [];

    foreach ($this->sourceSessionsForCategory as $session) {
        foreach ($session['exercises'] as $exerciseId) {
            if (!in_array($exerciseId, $exerciseIds)) {
                $exerciseIds[] = $exerciseId;
            }
            if (!isset($exerciseSessionMap[$exerciseId])) {
                $exerciseSessionMap[$exerciseId] = [];
            }
            $exerciseSessionMap[$exerciseId][] = [
                'uuid' => $session['uuid'],
                'name' => $session['name'],
            ];
        }
    }

    $exercises = Exercise::whereIn('id', $exerciseIds)->get();

    return $exercises->map(function ($exercise) use ($exerciseSessionMap) {
        $exercise->sessions = $exerciseSessionMap[$exercise->id] ?? [];
        return $exercise;
    });
});

$sessionsPerWeekForCategory = computed(function () {
    if (!$this->block || !$this->activeCategory) {
        return 0;
    }

    $count = 0;
    $firstWeek = collect($this->block->getChildren())->first(fn($week) => !$week->isLinked());

    if ($firstWeek) {
        foreach ($firstWeek->getChildren() as $session) {
            if ($session->getData()->category === $this->activeCategory) {
                $count++;
            }
        }
    }

    return $count;
});

$weekCount = computed(function () {
    if (!$this->block) {
        return 0;
    }
    return count($this->block->getChildren());
});

$setCount = computed(function () {
    return count($this->parseSets());
});

$getExerciseRows = function (int $exerciseId): array {
    if (!$this->block || !$this->activeCategory) {
        return [];
    }

    $rows = [];
    $defaultSetsArray = $this->parseSets();
    $defaultWeight = 50;

    foreach ($this->block->getChildren() as $weekIndex => $week) {
        $sessionNumber = 0;

        $resolvedWeek = $week->isLinked() ? collect($this->block->getChildren())->first(fn($w) => !$w->isLinked()) : $week;

        if (!$resolvedWeek) {
            continue;
        }

        foreach ($resolvedWeek->getChildren() as $session) {
            if ($session->getData()->category !== $this->activeCategory) {
                continue;
            }

            $sessionNumber++;

            $resolvedSession = $session->isLinked() ? $this->findSessionByUuid($session->linked_to) : $session;

            $exerciseIds = $resolvedSession?->getData()->exercises ?? [];
            if (!in_array($exerciseId, $exerciseIds)) {
                continue;
            }

            $reps = $defaultSetsArray;
            $weights = array_fill(0, count($defaultSetsArray), $defaultWeight);

            if ($resolvedSession) {
                foreach ($resolvedSession->getChildren() as $exercise) {
                    if ($exercise->getData()->exercise !== $exerciseId) {
                        continue;
                    }

                    $reps = [];
                    $weights = [];

                    foreach ($exercise->getChildren() as $set) {
                        $reps[] = $set->getData()->reps;
                        $weights[] = $set->getData()->weight;
                    }

                    break;
                }
            }

            $weekOverrides = $week->getData()->progressionOverrides;
            $sessionKey = $session->getData()->day . '-' . $session->getData()->slot;
            $overrides = $weekOverrides[$sessionKey][$exerciseId] ?? [];

            $repsOverridden = [];
            $weightsOverridden = [];

            foreach ($overrides as $setIndex => $override) {
                if (array_key_exists('reps', $override)) {
                    $reps[$setIndex] = $override['reps'] ?? '-';
                    $repsOverridden[$setIndex] = true;
                }
                if (array_key_exists('weight', $override)) {
                    $weights[$setIndex] = $override['weight'] ?? '-';
                    $weightsOverridden[$setIndex] = true;
                }
            }

            $rows[] = [
                'week' => $weekIndex + 1,
                'weekUuid' => $week->uuid,
                'session' => $sessionNumber,
                'sessionDay' => $session->getData()->day,
                'sessionSlot' => $session->getData()->slot,
                'reps' => $reps,
                'weights' => $weights,
                'repsOverridden' => $repsOverridden,
                'weightsOverridden' => $weightsOverridden,
            ];
        }
    }

    return $rows;
};

$findSessionByUuid = function (string $uuid): ?TrainingNode {
    if (!$this->block) {
        return null;
    }

    foreach ($this->block->getChildren() as $week) {
        foreach ($week->getChildren() as $session) {
            if ($session->uuid === $uuid) {
                return $session;
            }
        }
    }

    return null;
};

$findSessionInWeek = function (string $weekUuid, int $day, int $slot): ?TrainingNode {
    if (!$this->block) {
        return null;
    }

    foreach ($this->block->getChildren() as $week) {
        if ($week->uuid !== $weekUuid) {
            continue;
        }
        foreach ($week->getChildren() as $session) {
            $sessionData = $session->getData();
            if ($sessionData->day === $day && $sessionData->slot === $slot) {
                return $session;
            }
        }
    }

    return null;
};

$setActiveCategory = function (int $categoryId) {
    $this->activeCategory = $categoryId;
};

$updateProgressionOverride = function (array $data) {
    $this->dispatch('schedule-action', action: 'session.updateProgressionOverride', params: [
        'weekUuid' => $data['weekUuid'],
        'sessionDay' => $data['sessionDay'],
        'sessionSlot' => $data['sessionSlot'],
        'exerciseId' => $data['exerciseId'],
        'setIndex' => $data['setIndex'],
        'field' => $data['field'],
        'value' => $data['value'],
    ]);
    $this->skipRender();
};

on([
    'grid-refresh' => function ($block) {
        $this->block = TrainingNode::from($block);

        if (!$this->activeCategory && $this->uniqueCategoriesInBlock->isNotEmpty()) {
            $this->activeCategory = $this->uniqueCategoriesInBlock->first()->id;
        }

        $this->dispatch('progression-categories-updated', categories: $this->uniqueCategoriesInBlock->map(fn($cat) => ['id' => $cat->id, 'name' => $cat->name])->values()->toArray(), activeCategory: $this->activeCategory);
    },
    'progression-category-changed' => function ($categoryId) {
        $this->activeCategory = $categoryId;
    },
]);

?>

<div @cell-changed.window="$wire.updateProgressionOverride($event.detail)">
    @if ($block && $activeCategory)
        <div class="flex flex-wrap gap-4">
            @forelse ($this->exercisesForCategory as $exercise)
                <div wire:key="exercise-{{ $exercise->id }}" class="flex-shrink-0">
                    <x-debug-data-table
                        :title="$exercise->name . ' (' . (collect($exercise->sessions)->pluck('name')->filter()->join(', ') ?: 'No sessions') . ')'"
                        :rows="$this->getExerciseRows($exercise->id)"
                        :set-count="$this->setCount"
                        :exercise-id="$exercise->id"
                        row-color="bg-blue-50" />
                </div>
            @empty
                <div class="text-center py-8 text-zinc-500">
                    No exercises in this category
                </div>
            @endforelse
        </div>
    @else
        <div class="text-center py-8 text-zinc-500">
            No block data available
        </div>
    @endif
</div>
