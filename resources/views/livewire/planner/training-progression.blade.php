<?php

use App\Models\Exercise\Exercise;
use App\Models\Training\Progression\Athlete\AthleteData;
use App\Models\Training\Progression\Config\BlockOverview;
use App\Models\Training\Progression\Config\ProgressionConfig;
use App\Models\Training\Progression\Override\OverrideStore;
use App\Models\Training\Progression\Strategy\ProgressionCalculator;
use App\Models\Training\TrainingNode;
use function Livewire\Volt\{state, computed, on};

state([
    'block' => null,
    'progressionConfig' => null,
    'overrideStore' => null,
    'athleteData' => null,
    'exerciseProgressions' => [],
]);

$sourceSessions = computed(function () {
    if (!$this->block) {
        return [];
    }

    $sessions = [];
    $firstWeek = collect($this->block->getChildren())->first(fn($week) => !$week->isLinked());

    if ($firstWeek) {
        foreach ($firstWeek->getChildren() as $session) {
            if ($session->isLinked()) {
                continue;
            }
            $sessions[] = [
                'uuid' => $session->uuid,
                'name' => $session->getData()->name,
                'exercises' => $session->getData()->exercises ?? [],
            ];
        }
    }

    return $sessions;
});

$allExercises = computed(function () {
    if (!$this->block) {
        return collect();
    }

    $exerciseIds = [];
    $exerciseSessionMap = [];

    foreach ($this->sourceSessions as $session) {
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
    if (!$this->block) {
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
                'projected1RM' => $progression?->weeks[$weekIndex]?->projected1RM,
                'derived1RM' => $progression?->weeks[$weekIndex]?->getDerived1RM(),
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

on([
    'grid-refresh' => function ($block, $progressionConfig = null, $overrideStore = null, $athleteData = null) {
        $this->block = TrainingNode::from($block);
        $this->progressionConfig = $progressionConfig;
        $this->overrideStore = $overrideStore;
        $this->athleteData = $athleteData;
        $this->exerciseProgressions = [];
    },
    'athlete-data-saved' => function (array $data) {
        $this->dispatch('schedule-action', action: 'athlete.update', params: [
            'athleteId' => $data['athleteId'],
            'tests' => $data['tests'],
        ]);
    },
    'progression-config-saved' => function (array $data) {
        $this->dispatch('schedule-action', action: 'progression.update', params: $data);
    },
]);

?>

<div class="grid grid-cols-12 gap-4">
    @if ($block)
        @php $overview = $this->blockOverview; @endphp
        <flux:card x-data="{ expanded: true }" class="col-span-6">
            <button
                type="button"
                @click="expanded = !expanded"
                class="flex w-full items-center justify-between"
            >
                <flux:heading size="sm">Overview</flux:heading>
                <flux:icon
                    name="chevron-down"
                    class="size-5 text-zinc-400 transition-transform duration-200"
                    ::class="expanded ? 'rotate-180' : ''"
                />
            </button>
            <div x-show="expanded" x-collapse>
                <div class="pt-4">
                    <div class="flex flex-wrap gap-3">
                        <x-stat-card label="Weeks" :value="$overview->weekCount" />
                        <x-stat-card label="Total Sessions" :value="$overview->totalSessions" />
                        <x-stat-card label="Sessions/Week" :value="$overview->getSessionsPerWeekLabel()" />
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card x-data="{ expanded: true }" class="col-span-6">
            <button
                type="button"
                @click="expanded = !expanded"
                class="flex w-full items-center justify-between"
            >
                <flux:heading size="sm">Progression</flux:heading>
                <flux:icon
                    name="chevron-down"
                    class="size-5 text-zinc-400 transition-transform duration-200"
                    ::class="expanded ? 'rotate-180' : ''"
                />
            </button>
            <div x-show="expanded" x-collapse>
                <div class="pt-4">
                    <div class="flex flex-wrap gap-3">
                        <x-stat-card label="Weight" :value="$overview->weightStrategy" />
                        <x-stat-card label="Reps" :value="$overview->repStrategy" />
                        <x-stat-card label="Target" :value="$overview->getTargetImprovementLabel()" />
                    </div>
                    <div class="flex justify-end mt-4">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="cog-6-tooth"
                            wire:click="$dispatch('open-progression-modal', { progressionConfig: {{ Js::from($progressionConfig) }} })"
                        >
                            Configure
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card x-data="{ expanded: true }" class="col-span-6">
            <button
                type="button"
                @click="expanded = !expanded"
                class="flex w-full items-center justify-between"
            >
                <flux:heading size="sm">Athlete</flux:heading>
                <flux:icon
                    name="chevron-down"
                    class="size-5 text-zinc-400 transition-transform duration-200"
                    ::class="expanded ? 'rotate-180' : ''"
                />
            </button>
            <div x-show="expanded" x-collapse>
                <div class="pt-4">
                    @if ($athleteData && !empty($athleteData['tests']))
                        @php
                            $firstTest = collect($athleteData['tests'])->first();
                            $derived1RM = $firstTest ? round($firstTest['weight'] / \App\Models\Training\Progression\Reference\RepPercentageTable::getPercentage($firstTest['reps']), 1) : null;
                        @endphp
                        <div class="flex flex-wrap gap-3">
                            <x-stat-card label="Test Result" :value="$firstTest['reps'] . ' reps × ' . $firstTest['weight'] . 'kg'" />
                            <x-stat-card label="Derived 1RM" :value="$derived1RM . 'kg'" />
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">No athlete data configured.</p>
                    @endif
                    <div class="flex justify-end mt-4">
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="cog-6-tooth"
                            wire:click="$dispatch('open-athlete-modal', { athleteData: {{ Js::from($athleteData) }} })"
                        >
                            Configure
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card x-data="{ expanded: true }" class="col-span-12">
            <button
                type="button"
                @click="expanded = !expanded"
                class="flex w-full items-center justify-between"
            >
                <flux:heading size="sm">Exercises</flux:heading>
                <flux:icon
                    name="chevron-down"
                    class="size-5 text-zinc-400 transition-transform duration-200"
                    ::class="expanded ? 'rotate-180' : ''"
                />
            </button>
            <div x-show="expanded" x-collapse>
                <div class="pt-4">
                    @if ($this->allExercises->isNotEmpty())
                        <div class="flex flex-wrap gap-4">
                            @foreach ($this->allExercises as $exercise)
                                <div wire:key="exercise-{{ $exercise->id }}" class="flex-shrink-0">
                                    <x-progression-table
                                        :title="$exercise->name . ' (' . (collect($exercise->sessions)->pluck('name')->filter()->join(', ') ?: 'No sessions') . ')'"
                                        :rows="$this->getExerciseRows($exercise->id)"
                                        :set-count="$this->setCount"
                                        :exercise-id="$exercise->id"
                                        :progression="$this->getExerciseProgression($exercise->id)"
                                        :group-by-week="true"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-zinc-500">
                            No exercises in this block
                        </div>
                    @endif
                </div>
            </div>
        </flux:card>
    @else
        <div class="text-center py-8 text-zinc-500">
            No block data available
        </div>
    @endif

    <livewire:planner.athlete-modal />
    <livewire:planner.progression-modal />
</div>
