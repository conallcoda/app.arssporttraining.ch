<?php

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Exercise\Exercise;
use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Config\BlockOverview;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Strategy\ProgressionCalculator;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use function Livewire\Volt\{state, computed, on};

state([
    'block' => null,
    'activeCategory' => null,
    'progressionConfig' => null,
    'overrideStore' => null,
    'athleteData' => null,
    'exerciseProgressions' => [],
]);

$categories = computed(fn() => TrainingSessionCategory::all());

$categoriesById = computed(fn() => $this->categories->keyBy('id'));

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

$blockOverview = computed(function () {
    if (!$this->block || !$this->progressionConfig) {
        return new BlockOverview(
            weekCount: 0,
            totalSessions: 0,
            minSessionsPerWeek: 0,
            maxSessionsPerWeek: 0,
            weightStrategy: 'Not set',
            repStrategy: 'Not set',
            targetImprovement: 0,
        );
    }

    $totalSessions = 0;
    $counts = [];

    foreach ($this->block->getChildren() as $week) {
        $resolvedWeek = $week->isLinked()
            ? collect($this->block->getChildren())->first(fn($w) => !$w->isLinked())
            : $week;
        if ($resolvedWeek) {
            $count = count($resolvedWeek->getChildren());
            $totalSessions += $count;
            $counts[] = $count;
        }
    }

    $config = ProgressionConfig::from($this->progressionConfig);

    return $config->buildOverview(
        totalSessions: $totalSessions,
        minSessionsPerWeek: !empty($counts) ? min($counts) : 0,
        maxSessionsPerWeek: !empty($counts) ? max($counts) : 0,
    );
});

$buildSessionMap = function (): array {
    if (!$this->block) {
        return [];
    }

    $sessionMap = [];
    $sourceWeek = collect($this->block->getChildren())->first(fn($week) => !$week->isLinked());

    if (!$sourceWeek) {
        return [];
    }

    $sourceSessions = [];
    foreach ($sourceWeek->getChildren() as $session) {
        $sourceSessions[$session->uuid] = [
            'day' => $session->getData()->day,
            'slot' => $session->getData()->slot,
        ];
    }

    foreach ($this->block->getChildren() as $weekIndex => $week) {
        $sessionMap[$weekIndex] = $sourceSessions;
    }

    return $sessionMap;
};

$getExerciseProgression = function (int $exerciseId) {
    if (isset($this->exerciseProgressions[$exerciseId])) {
        return $this->exerciseProgressions[$exerciseId];
    }

    if (!$this->block || !$this->progressionConfig) {
        return null;
    }

    $config = ProgressionConfig::from($this->progressionConfig);
    $overrides = $this->overrideStore ? OverrideStore::from($this->overrideStore) : new OverrideStore();
    $athlete = $this->athleteData ? AthleteData::from($this->athleteData) : null;

    $sessionMap = $this->buildSessionMap();
    $calculator = new ProgressionCalculator($config, $overrides, $athlete);

    $progression = $calculator->calculateExerciseProgression($exerciseId, $sessionMap);
    $this->exerciseProgressions[$exerciseId] = $progression;

    return $progression;
};

$getExerciseRows = function (int $exerciseId): array {
    if (!$this->block || !$this->activeCategory) {
        return [];
    }

    $progression = $this->getExerciseProgression($exerciseId);
    $rows = [];

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

            $reps = [];
            $weights = [];
            $sources = [];

            if ($progression) {
                $weekResult = $progression->weeks[$weekIndex] ?? null;
                $sessionResult = $weekResult?->getSession($session->uuid);

                if ($sessionResult) {
                    foreach ($sessionResult->sets as $set) {
                        $reps[] = $set->reps;
                        $weights[] = $set->weight;
                        $sources[] = $set->source;
                    }
                }
            }

            if (empty($reps)) {
                $reps = [10, 10, 8, 8];
                $weights = [50, 50, 50, 50];
                $sources = ['computed', 'computed', 'computed', 'computed'];
            }

            $rows[] = [
                'week' => $weekIndex + 1,
                'weekIndex' => $weekIndex,
                'weekUuid' => $week->uuid,
                'session' => $sessionNumber,
                'sessionUuid' => $session->uuid,
                'sessionDay' => $session->getData()->day,
                'sessionSlot' => $session->getData()->slot,
                'reps' => $reps,
                'weights' => $weights,
                'sources' => $sources,
                'anchorWeight' => $progression?->weeks[$weekIndex]?->anchorWeight,
                'anchorReps' => $progression?->weeks[$weekIndex]?->anchorReps,
            ];
        }
    }

    return $rows;
};

$setCount = computed(function () {
    if (!$this->progressionConfig) {
        return 4;
    }

    $startingReps = $this->progressionConfig['startingReps'] ?? 12;

    return $startingReps >= 12 ? 4 : 3;
});

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

on([
    'grid-refresh' => function ($block, $progressionConfig = null, $overrideStore = null, $athleteData = null) {
        $this->block = TrainingNode::from($block);
        $this->progressionConfig = $progressionConfig;
        $this->overrideStore = $overrideStore;
        $this->athleteData = $athleteData;
        $this->exerciseProgressions = [];

        if (!$this->activeCategory && $this->uniqueCategoriesInBlock->isNotEmpty()) {
            $this->activeCategory = $this->uniqueCategoriesInBlock->first()->id;
        }
    },
]);

?>

<div>
    @if ($block)
        <flux:accordion transition>
            <flux:accordion.item expanded>
                <flux:accordion.heading>Overview</flux:accordion.heading>
                <flux:accordion.content>
                    <div class="py-4">
                        @php $overview = $this->blockOverview; @endphp
                        <div class="flex flex-wrap gap-3">
                            <x-stat-card label="Weeks" :value="$overview->weekCount" />
                            <x-stat-card label="Total Sessions" :value="$overview->totalSessions" />
                            <x-stat-card label="Sessions/Week" :value="$overview->getSessionsPerWeekLabel()" />
                            <x-stat-card label="Weight" :value="$overview->weightStrategy" />
                            <x-stat-card label="Reps" :value="$overview->repStrategy" />
                            <x-stat-card label="Target" :value="$overview->getTargetImprovementLabel()" />
                        </div>
                    </div>
                </flux:accordion.content>
            </flux:accordion.item>

            @if ($athleteData && !empty($athleteData['tests']))
                @php
                    $firstTest = collect($athleteData['tests'])->first();
                    $derived1RM = $firstTest ? round($firstTest['weight'] / \App\Models\Training\Progression\Reference\RepPercentageTable::getPercentage($firstTest['reps']), 1) : null;
                @endphp
                <flux:accordion.item expanded>
                    <flux:accordion.heading>Athlete</flux:accordion.heading>
                    <flux:accordion.content>
                        <div class="py-4">
                            <div class="flex flex-wrap gap-3">
                                <x-stat-card label="Test Result" :value="$firstTest['reps'] . ' reps × ' . $firstTest['weight'] . 'kg'" />
                                <x-stat-card label="Derived 1RM" :value="$derived1RM . 'kg'" />
                            </div>
                        </div>
                    </flux:accordion.content>
                </flux:accordion.item>
            @endif

            <flux:accordion.item expanded>
                <flux:accordion.heading>Exercises</flux:accordion.heading>
                <flux:accordion.content>
                    <div class="py-4">
                        @if ($this->uniqueCategoriesInBlock->isNotEmpty())
                            <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700 mb-4">
                                @foreach ($this->uniqueCategoriesInBlock as $category)
                                    @php
                                        $dotColor = $this->getColorValue($category->background_color, 500);
                                        $isActive = $activeCategory === $category->id;
                                    @endphp
                                    <button
                                        wire:click="setActiveCategory({{ $category->id }})"
                                        class="flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $isActive ? 'border-zinc-800 dark:border-white text-zinc-900 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                                    >
                                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $dotColor }};"></span>
                                        {{ $category->name }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap gap-4">
                                @forelse ($this->exercisesForCategory as $exercise)
                                    <div wire:key="exercise-{{ $activeCategory }}-{{ $exercise->id }}" class="flex-shrink-0">
                                        <x-progression-table
                                            :title="$exercise->name . ' (' . (collect($exercise->sessions)->pluck('name')->filter()->join(', ') ?: 'No sessions') . ')'"
                                            :rows="$this->getExerciseRows($exercise->id)"
                                            :set-count="$this->setCount"
                                            :exercise-id="$exercise->id"
                                            :progression="$this->getExerciseProgression($exercise->id)"
                                        />
                                    </div>
                                @empty
                                    <div class="text-center py-8 text-zinc-500 w-full">
                                        No exercises in this category
                                    </div>
                                @endforelse
                            </div>
                        @else
                            <div class="text-center py-8 text-zinc-500">
                                No session categories in this block
                            </div>
                        @endif
                    </div>
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    @else
        <div class="text-center py-8 text-zinc-500">
            No block data available
        </div>
    @endif
</div>
