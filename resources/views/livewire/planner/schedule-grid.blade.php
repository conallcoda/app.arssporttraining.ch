<?php

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingSessionCategory;
use function Livewire\Volt\{state, computed, on};

state([
    'block' => null,
    'compact' => false,
]);

$categories = computed(fn() => TrainingSessionCategory::all());

$categoriesById = computed(fn() => $this->categories->keyBy('id'));

$days = computed(fn() => $this->compact
    ? ['M', 'T', 'W', 'T', 'F', 'S', 'S']
    : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
);

$slots = computed(fn() => $this->compact
    ? ['AM', 'PM']
    : ['Morning', 'Afternoon']
);

$getWeekLabel = function (int $weekIndex): string {
    return $this->compact ? 'W' . ($weekIndex + 1) : 'Week ' . ($weekIndex + 1);
};

on(['compact-mode-changed' => function (bool $compact) {
    $this->compact = $compact;
}]);

$getColorValue = function (?string $colorName, int $shade = 500): string {
    if (!$colorName) {
        return '#000000';
    }
    return ColorPicker::getColorValue($colorName, $shade);
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

$getScheduleCategory = function (string $weekUuid, int $slot, int $day): ?int {
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

$getWeekLinkInfo = function (string $weekUuid): ?array {
    foreach ($this->block->getChildren() as $weekIndex => $week) {
        if ($week->uuid === $weekUuid && $week->isLinked()) {
            $linkedToIndex = null;
            foreach ($this->block->getChildren() as $i => $w) {
                if ($w->uuid === $week->linked_to) {
                    $linkedToIndex = $i + 1;
                    break;
                }
            }
            return [
                'weekIndex' => $weekIndex + 1,
                'linkedToIndex' => $linkedToIndex,
            ];
        }
    }
    return null;
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

    foreach ($this->block->getChildren() as $weekIndex => $week) {
        if ($week->uuid === $currentWeekUuid) {
            continue;
        }
        if ($week->linked_to !== null) {
            continue;
        }
        $weeks[] = [
            'uuid' => $week->uuid,
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
        'linkedTo' => $week?->linked_to,
        'availableWeeks' => $this->getAvailableWeeksForLinking($weekUuid),
        'isAddMode' => false,
    ]);
};

$getAvailableWeeksForNewWeekLinking = function (): array {
    $weeks = [];

    foreach ($this->block->getChildren() as $weekIndex => $week) {
        if ($week->linked_to !== null) {
            continue;
        }
        $weeks[] = [
            'uuid' => $week->uuid,
            'weekIndex' => $weekIndex,
        ];
    }

    return $weeks;
};

$openAddWeekModal = function () {
    $nextWeekIndex = count($this->block->getChildren());

    $this->dispatch('open-week-modal', [
        'weekUuid' => null,
        'weekIndex' => $nextWeekIndex,
        'linkedTo' => null,
        'availableWeeks' => $this->getAvailableWeeksForNewWeekLinking(),
        'isAddMode' => true,
    ]);
};

on(['session-saved' => function (array $data) {
    $weekIndex = $this->getWeekIndex($data['weekUuid']);

    if ($data['sessionUuid']) {
        $this->dispatch('schedule-action', action: 'session.update', params: [
            'sessionId' => $data['sessionUuid'],
            'name' => $data['name'],
            'day' => $data['day'],
            'slot' => $data['slot'],
            'category' => $data['category'],
            'exercises' => $data['exercises'],
        ]);
    } else {
        $this->dispatch('schedule-action', action: 'session.add', params: [
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

    $this->dispatch('schedule-action', action: 'session.link', params: [
        'weekIndex' => $weekIndex,
        'day' => $data['day'],
        'slot' => $data['slot'],
        'linkedSessionUuid' => $data['linkedSessionUuid'],
    ]);
}]);

on(['session-link-updated' => function (array $data) {
    $this->dispatch('schedule-action', action: 'session.updateLink', params: [
        'sessionId' => $data['sessionUuid'],
        'linkedTo' => $data['linkedTo'],
    ]);
}]);

on(['session-deleted' => function (array $data) {
    $weekIndex = $this->getWeekIndex($data['weekUuid']);

    $this->dispatch('schedule-action', action: 'session.delete', params: [
        'weekIndex' => $weekIndex,
        'slot' => $data['slot'],
        'day' => $data['day'],
    ]);
}]);

on(['week-linked' => function (array $data) {
    $this->dispatch('schedule-action', action: 'week.link', params: [
        'weekUuid' => $data['weekUuid'],
        'weekIndex' => $data['weekIndex'],
        'linkedTo' => $data['linkedTo'],
    ]);
}]);

on(['week-deleted' => function (array $data) {
    $this->dispatch('schedule-action', action: 'week.delete', params: [
        'weekUuid' => $data['weekUuid'],
        'weekIndex' => $data['weekIndex'],
    ]);
}]);

on(['week-added' => function (array $data) {
    $this->dispatch('schedule-action', action: 'week.add', params: [
        'linkedTo' => $data['linkedTo'],
    ]);
}]);

on(['grid-refresh' => function ($block) {
    $this->block = TrainingNode::from($block);
}]);

on(['session-move' => function (string $sessionId, int $newDay, int $newSlot) {
    $this->dispatch('schedule-action', action: 'session.move', params: [
        'sessionId' => $sessionId,
        'newDay' => $newDay,
        'newSlot' => $newSlot,
    ]);
}]);

on(['session-swap' => function (string $session1Id, string $session2Id) {
    $this->dispatch('schedule-action', action: 'session.swap', params: [
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

    get cardWidth() {
        return $store.cardWidths['block-creator-schedule'] || 50;
    },

    formatName(name) {
        if (!name) return '';
        return this.cardWidth < 50 ? name.substring(0, 3) : name;
    },

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
    },

    confirmUnlinkAndAction(weekLinkInfo, action) {
        if (!weekLinkInfo) {
            action();
            return;
        }
        const message = `This action will unlink Week ${weekLinkInfo.weekIndex} from Week ${weekLinkInfo.linkedToIndex}`;
        if (confirm(message)) {
            action();
        }
    }
}" x-init="$nextTick(() => show = true)">
    @if ($block)
        <div x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="overflow-x-auto">
            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 text-sm w-full table-fixed">
                <colgroup>
                    <col class="{{ $compact ? 'w-10' : 'w-20' }}">
                    <col class="{{ $compact ? 'w-10' : 'w-28' }}">
                </colgroup>
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2" colspan="2"></th>
                        @foreach ($this->days as $dayIndex => $day)
                            <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 transition-all duration-300" wire:key="day-header-{{ $dayIndex }}">
                                {{ $day }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($block->getChildren() as $weekIndex => $week)
                        @foreach ($this->slots as $slotIndex => $slotName)
                            <tr class="{{ $week->isLinked() ? 'bg-zinc-50 dark:bg-zinc-900/50' : '' }}">
                                @if ($slotIndex === 0)
                                    <td class="border {{ $week->isLinked() ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} px-2 py-2 font-medium bg-zinc-50 dark:bg-zinc-800/50 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-all duration-300 whitespace-nowrap"
                                        rowspan="2"
                                        wire:key="week-label-{{ $weekIndex }}"
                                        wire:dblclick="openWeekModal('{{ $week->uuid }}', {{ $weekIndex }})">
                                        <div class="flex flex-col items-center gap-0.5">
                                            <span>{{ $this->getWeekLabel($weekIndex) }}</span>
                                            @if ($week->isLinked())
                                                <x-lucide-lock class="w-3 h-3 opacity-70" />
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td class="border {{ $week->isLinked() ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} {{ $compact ? 'px-1' : 'px-3' }} py-2 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 transition-all duration-300 whitespace-nowrap text-center"
                                    wire:key="slot-label-{{ $weekIndex }}-{{ $slotIndex }}">
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
                                    <td class="border {{ $week->isLinked() ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} p-1 h-12 transition-all duration-200"
                                        :class="{
                                            'bg-blue-100 dark:bg-blue-900/30': isDraggingOver === '{{ $cellKey }}' && !isDropDisallowed,
                                            'bg-red-100 dark:bg-red-900/30 cursor-not-allowed': isDraggingOver === '{{ $cellKey }}' && isDropDisallowed
                                        }"
                                        @dragover.prevent="dragOver($event, {{ $day }}, {{ $slotIndex }}, '{{ $session?->uuid }}', '{{ $sessionLinkedTo }}')"
                                        @dragleave="dragLeave()"
                                        @drop.prevent="drop('{{ $session?->uuid }}', '{{ $sessionLinkedTo }}', {{ $day }}, {{ $slotIndex }})">
                                        @if ($category)
                                            @php
                                                $bgColor = $this->getColorValue($category->background_color);
                                                $lightBgColor = $this->getColorValue($category->background_color, 200);
                                                $borderColor = $this->getColorValue($category->background_color, 400);
                                                $isLinkedStyle = $week->isLinked() || $sessionIsLinked;
                                                $weekLinkInfo = $this->getWeekLinkInfo($week->uuid);
                                            @endphp
                                            <div class="h-full flex items-center justify-center gap-1 rounded px-2 py-1 transition-transform duration-200 cursor-move {{ $isLinkedStyle ? 'border-2 border-dashed' : '' }} cursor-pointer"
                                                :class="{
                                                    'opacity-50 scale-95': draggedSessionUuid === '{{ $session->uuid }}',
                                                    'cursor-not-allowed': isDraggingOver === '{{ $cellKey }}' && isDropDisallowed
                                                }"
                                                style="background-color: {{ $isLinkedStyle ? $lightBgColor : $bgColor }}; color: {{ $this->getColorValue($category->text_color) }}; {{ $isLinkedStyle ? 'border-color: ' . $borderColor . ';' : '' }}"
                                                draggable="true"
                                                @dragstart="startDrag($event, '{{ $session->uuid }}', '{{ $sessionLinkedTo }}', {{ $day }}, {{ $slotIndex }})"
                                                @dragend="draggedSessionUuid = null; draggedSessionLinkedTo = null; isDraggingOver = null; isDropDisallowed = false"
                                                @dblclick="confirmUnlinkAndAction({{ Js::from($weekLinkInfo) }}, () => $wire.openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }}))">
                                                <span class="text-xs font-medium" x-text="formatName('{{ $sessionName ?? $category->name }}')"></span>
                                            </div>
                                        @else
                                            @php
                                                $weekLinkInfo = $this->getWeekLinkInfo($week->uuid);
                                            @endphp
                                            <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded"
                                                @click="confirmUnlinkAndAction({{ Js::from($weekLinkInfo) }}, () => $wire.openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }}))">
                                                <x-lucide-plus class="w-4 h-4" />
                                            </div>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    @endforeach
                    <tr>
                        <td class="border border-zinc-300 dark:border-zinc-600 border-dashed px-3 py-4 bg-zinc-50/50 dark:bg-zinc-800/30 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
                            colspan="9"
                            wire:click="openAddWeekModal">
                            <div class="flex items-center justify-center gap-2 text-zinc-400 dark:text-zinc-500">
                                <x-lucide-plus class="w-4 h-4" />
                                <span class="text-sm font-medium">Add Week</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <livewire:planner.session-modal />
    <livewire:planner.week-modal />
</div>
