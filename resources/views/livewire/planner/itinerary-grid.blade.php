<?php

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use function Livewire\Volt\{state, computed, on};

state([
    'block' => null,
]);

$categories = computed(fn() => TrainingSessionCategory::all());

$categoriesById = computed(fn() => $this->categories->keyBy('id'));

$days = computed(fn() => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);

$slots = computed(fn() => ['Morning', 'Afternoon']);

$getColorValue = function (?string $colorName): string {
    if (!$colorName) {
        return '#000000';
    }
    return ColorPicker::getColorValue($colorName);
};

$getSessionAtPosition = function (string $weekUuid, int $slot, int $day): ?TrainingNode {
    foreach ($this->block->getChildren() as $week) {
        if ($week->uuid === $weekUuid) {
            foreach ($week->getChildren() as $session) {
                if ($session->getData()->day === $day && $session->getData()->slot === $slot) {
                    return $session;
                }
            }
        }
    }
    return null;
};

$getItinerary = function (string $weekUuid, int $slot, int $day): ?int {
    $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
    return $session?->getData()->category;
};

$getSessionName = function (string $weekUuid, int $slot, int $day): ?string {
    $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
    return $session?->getData()->name;
};

$isSessionLinked = function (string $weekUuid, int $slot, int $day): bool {
    $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
    return $session?->isLinked() ?? false;
};

$getWeekIndex = function (string $weekUuid): int {
    foreach ($this->block->getChildren() as $index => $week) {
        if ($week->uuid === $weekUuid) {
            return $index;
        }
    }
    return 0;
};

$getNonLinkedSessions = function (): array {
    $sessions = [];
    $firstBlock = $this->block;

    if (!$firstBlock) {
        return $sessions;
    }

    $firstWeek = collect($firstBlock->getChildren())->first(fn($week) => !$week->isLinked());

    if ($firstWeek) {
        foreach ($firstWeek->getChildren() as $session) {
            if ($session->linked_to !== null) {
                continue;
            }
            $sessions[] = [
                'uuid' => $session->uuid,
                'name' => $session->getData()->name,
                'category' => $session->getData()->category,
                'day' => $session->getData()->day,
                'slot' => $session->getData()->slot,
            ];
        }
    }

    return $sessions;
};

$openSessionModal = function (string $weekUuid, int $slot, int $day) {
    $existingSession = $this->getSessionAtPosition($weekUuid, $slot, $day);

    $this->dispatch('open-session-modal', [
        'weekUuid' => $weekUuid,
        'day' => $day,
        'slot' => $slot,
        'sessionUuid' => $existingSession?->uuid,
        'name' => $existingSession?->getData()->name,
        'category' => $existingSession?->getData()->category,
        'exercises' => $existingSession?->getData()->exercises ?? [],
        'availableSessions' => $this->getNonLinkedSessions(),
        'linkedTo' => $existingSession?->linked_to,
    ]);
};

$getAvailableWeeksForLinking = function (string $currentWeekUuid): array {
    $weeks = [];
    $blockIndex = 0;

    foreach ($this->block->getChildren() as $weekIndex => $week) {
        if ($week->uuid === $currentWeekUuid) {
            continue;
        }
        if ($week->linked_to !== null) {
            continue;
        }
        $weeks[] = [
            'uuid' => $week->uuid,
            'blockIndex' => $blockIndex,
            'weekIndex' => $weekIndex,
        ];
    }

    return $weeks;
};

$openWeekModal = function (string $weekUuid, int $weekIndex) {
    $week = null;
    foreach ($this->block->getChildren() as $w) {
        if ($w->uuid === $weekUuid) {
            $week = $w;
            break;
        }
    }

    $this->dispatch('open-week-modal', [
        'weekUuid' => $weekUuid,
        'weekIndex' => $weekIndex,
        'blockIndex' => 0,
        'linkedTo' => $week?->linked_to,
        'availableWeeks' => $this->getAvailableWeeksForLinking($weekUuid),
    ]);
};

on(['session-saved' => function (array $data) {
    $weekIndex = $this->getWeekIndex($data['weekUuid']);

    if ($data['sessionUuid']) {
        $this->dispatch('itinerary-action', action: 'session.update', params: [
            'sessionId' => $data['sessionUuid'],
            'name' => $data['name'],
            'day' => $data['day'],
            'slot' => $data['slot'],
            'category' => $data['category'],
            'exercises' => $data['exercises'],
        ]);
    } else {
        $this->dispatch('itinerary-action', action: 'session.add', params: [
            'weekIndex' => $weekIndex,
            'name' => $data['name'],
            'day' => $data['day'],
            'slot' => $data['slot'],
            'category' => $data['category'],
            'exercises' => $data['exercises'],
        ]);
    }
}]);

on(['session-linked' => function (array $data) {
    $weekIndex = $this->getWeekIndex($data['weekUuid']);

    $this->dispatch('itinerary-action', action: 'session.link', params: [
        'weekIndex' => $weekIndex,
        'day' => $data['day'],
        'slot' => $data['slot'],
        'linkedSessionUuid' => $data['linkedSessionUuid'],
    ]);
}]);

on(['session-link-updated' => function (array $data) {
    $this->dispatch('itinerary-action', action: 'session.updateLink', params: [
        'sessionId' => $data['sessionUuid'],
        'linkedTo' => $data['linkedTo'],
    ]);
}]);

on(['session-deleted' => function (array $data) {
    $weekIndex = $this->getWeekIndex($data['weekUuid']);

    $this->dispatch('itinerary-action', action: 'session.delete', params: [
        'weekIndex' => $weekIndex,
        'slot' => $data['slot'],
        'day' => $data['day'],
    ]);
}]);

on(['week-linked' => function (array $data) {
    $this->dispatch('itinerary-action', action: 'week.link', params: [
        'weekUuid' => $data['weekUuid'],
        'weekIndex' => $data['weekIndex'],
        'linkedTo' => $data['linkedTo'],
    ]);
}]);

on(['grid-refresh' => function ($block) {
    $this->block = TrainingNode::from($block);
}]);

on(['session-move' => function (string $sessionId, int $newDay, int $newSlot) {
    $this->dispatch('itinerary-action', action: 'session.move', params: [
        'sessionId' => $sessionId,
        'newDay' => $newDay,
        'newSlot' => $newSlot,
    ]);
}]);

on(['session-swap' => function (string $session1Id, string $session2Id) {
    $this->dispatch('itinerary-action', action: 'session.swap', params: [
        'session1Id' => $session1Id,
        'session2Id' => $session2Id,
    ]);
}]);

?>

<div x-data="{
    show: false,
    draggedSessionUuid: null,
    draggedSessionLinkedTo: null,
    draggedFromDay: null,
    draggedFromSlot: null,
    isDraggingOver: null,
    isDropDisallowed: false,

    startDrag(event, sessionUuid, linkedTo, day, slot) {
        this.draggedSessionUuid = sessionUuid;
        this.draggedSessionLinkedTo = linkedTo;
        this.draggedFromDay = day;
        this.draggedFromSlot = slot;
        event.dataTransfer.effectAllowed = 'move';
    },

    areLinkedToEachOther(targetUuid, targetLinkedTo) {
        if (!this.draggedSessionUuid || !targetUuid) return false;
        if (this.draggedSessionLinkedTo === targetUuid || targetLinkedTo === this.draggedSessionUuid) return true;
        if (this.draggedSessionLinkedTo && this.draggedSessionLinkedTo === targetLinkedTo) return true;
        return false;
    },

    dragOver(event, day, slot, targetUuid, targetLinkedTo) {
        this.isDraggingOver = day + '-' + slot;
        this.isDropDisallowed = this.areLinkedToEachOther(targetUuid, targetLinkedTo);
        event.dataTransfer.dropEffect = this.isDropDisallowed ? 'none' : 'move';
    },

    dragLeave() {
        this.isDraggingOver = null;
        this.isDropDisallowed = false;
    },

    drop(targetSessionUuid, targetLinkedTo, targetDay, targetSlot) {
        if (this.areLinkedToEachOther(targetSessionUuid, targetLinkedTo)) {
            this.draggedSessionUuid = null;
            this.draggedSessionLinkedTo = null;
            this.draggedFromDay = null;
            this.draggedFromSlot = null;
            this.isDraggingOver = null;
            this.isDropDisallowed = false;
            return;
        }

        if (this.draggedSessionUuid && (this.draggedFromDay !== targetDay || this.draggedFromSlot !== targetSlot)) {
            if (targetSessionUuid) {
                $wire.dispatch('session-swap', {
                    session1Id: this.draggedSessionUuid,
                    session2Id: targetSessionUuid
                });
            } else {
                $wire.dispatch('session-move', {
                    sessionId: this.draggedSessionUuid,
                    newDay: targetDay,
                    newSlot: targetSlot
                });
            }
        }
        this.draggedSessionUuid = null;
        this.draggedSessionLinkedTo = null;
        this.draggedFromDay = null;
        this.draggedFromSlot = null;
        this.isDraggingOver = null;
        this.isDropDisallowed = false;
    }
}" x-init="$nextTick(() => show = true)">
    @if ($block)
        <div x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="overflow-x-auto">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 text-sm w-full">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2" colspan="2"></th>
                        @foreach ($this->days as $day)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 min-w-[80px]">
                                {{ $day }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($block->getChildren() as $weekIndex => $week)
                        @foreach ($this->slots as $slotIndex => $slotName)
                            <tr class="{{ $week->isLinked() ? 'opacity-50' : '' }}">
                                @if ($slotIndex === 0)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium bg-zinc-50 dark:bg-zinc-800/50 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700/50"
                                        rowspan="2"
                                        wire:click="openWeekModal('{{ $week->uuid }}', {{ $weekIndex }})">
                                        <div class="flex items-center gap-1">
                                            Week {{ $weekIndex + 1 }}
                                            @if ($week->isLinked())
                                                <x-lucide-link class="w-3 h-3 opacity-70" />
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td
                                    class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50">
                                    {{ $slotName }}
                                </td>
                                @for ($day = 0; $day < 7; $day++)
                                    @php
                                        $session = $this->getSessionAtPosition($week->uuid, $slotIndex, $day);
                                        $categoryId = $session?->getData()->category;
                                        $category = $categoryId
                                            ? $this->categoriesById[$categoryId] ?? null
                                            : null;
                                        $sessionName = $session?->getData()->name;
                                        $sessionIsLinked = $session !== null && $session->linked_to !== null;
                                        $sessionLinkedTo = $session?->linked_to;
                                        $cellKey = $day . '-' . $slotIndex;
                                    @endphp
                                    <td class="border border-zinc-300 dark:border-zinc-600 p-1 h-12 {{ $week->isLinked() ? 'pointer-events-none' : '' }} {{ $sessionIsLinked && !$week->isLinked() ? 'opacity-50' : '' }} transition-all duration-200"
                                        :class="{
                                            'bg-blue-100 dark:bg-blue-900/30': isDraggingOver === '{{ $cellKey }}' && !isDropDisallowed,
                                            'bg-red-100 dark:bg-red-900/30 cursor-not-allowed': isDraggingOver === '{{ $cellKey }}' && isDropDisallowed
                                        }"
                                        @if(!$week->isLinked())
                                            @dragover.prevent="dragOver($event, {{ $day }}, {{ $slotIndex }}, '{{ $session?->uuid }}', '{{ $sessionLinkedTo }}')"
                                            @dragleave="dragLeave()"
                                            @drop.prevent="drop('{{ $session?->uuid }}', '{{ $sessionLinkedTo }}', {{ $day }}, {{ $slotIndex }})"
                                        @endif>
                                        @if ($category)
                                            <div class="h-full flex items-center justify-center gap-1 rounded px-2 py-1 {{ !$week->isLinked() ? 'cursor-move' : '' }} transition-transform duration-200"
                                                :class="{
                                                    'opacity-50 scale-95': draggedSessionUuid === '{{ $session->uuid }}',
                                                    'cursor-not-allowed': isDraggingOver === '{{ $cellKey }}' && isDropDisallowed
                                                }"
                                                style="background-color: {{ $this->getColorValue($category->background_color) }}; color: {{ $this->getColorValue($category->text_color) }};"
                                                @if(!$week->isLinked())
                                                    draggable="true"
                                                    @dragstart="startDrag($event, '{{ $session->uuid }}', '{{ $sessionLinkedTo }}', {{ $day }}, {{ $slotIndex }})"
                                                    @dragend="draggedSessionUuid = null; draggedSessionLinkedTo = null; isDraggingOver = null; isDropDisallowed = false"
                                                    wire:dblclick="openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }})"
                                                @endif>
                                                <span class="text-xs font-medium">{{ $sessionName ?? $category->name }}</span>
                                                @if ($sessionIsLinked || $week->isLinked())
                                                    <x-lucide-link class="w-3 h-3 opacity-70" />
                                                @endif
                                            </div>
                                        @else
                                            <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded"
                                                @if(!$week->isLinked()) wire:click="openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }})" @endif>
                                                <x-lucide-plus class="w-4 h-4" />
                                            </div>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <livewire:planner.session-modal />
    <livewire:planner.week-modal />
</div>
