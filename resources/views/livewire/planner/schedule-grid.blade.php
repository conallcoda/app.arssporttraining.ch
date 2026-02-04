<?php

use App\Models\Training\TrainingNode;
use function Livewire\Volt\{state, computed, on};

state([
    'block' => null,
    'compact' => false,
]);

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
        return '#3b82f6';
    }

    if ($colorName === 'black') {
        return '#000000';
    }

    if ($colorName === 'white') {
        return '#ffffff';
    }

    $palettes = [
        'slate' => [50 => '#f8fafc', 100 => '#f1f5f9', 200 => '#e2e8f0', 300 => '#cbd5e1', 400 => '#94a3b8', 500 => '#64748b', 600 => '#475569', 700 => '#334155', 800 => '#1e293b', 900 => '#0f172a', 950 => '#020617'],
        'gray' => [50 => '#f9fafb', 100 => '#f3f4f6', 200 => '#e5e7eb', 300 => '#d1d5db', 400 => '#9ca3af', 500 => '#6b7280', 600 => '#4b5563', 700 => '#374151', 800 => '#1f2937', 900 => '#111827', 950 => '#030712'],
        'zinc' => [50 => '#fafafa', 100 => '#f4f4f5', 200 => '#e4e4e7', 300 => '#d4d4d8', 400 => '#a1a1aa', 500 => '#71717a', 600 => '#52525b', 700 => '#3f3f46', 800 => '#27272a', 900 => '#18181b', 950 => '#09090b'],
        'neutral' => [50 => '#fafafa', 100 => '#f5f5f5', 200 => '#e5e5e5', 300 => '#d4d4d4', 400 => '#a3a3a3', 500 => '#737373', 600 => '#525252', 700 => '#404040', 800 => '#262626', 900 => '#171717', 950 => '#0a0a0a'],
        'stone' => [50 => '#fafaf9', 100 => '#f5f5f4', 200 => '#e7e5e4', 300 => '#d6d3d1', 400 => '#a8a29e', 500 => '#78716c', 600 => '#57534e', 700 => '#44403c', 800 => '#292524', 900 => '#1c1917', 950 => '#0c0a09'],
        'red' => [50 => '#fef2f2', 100 => '#fee2e2', 200 => '#fecaca', 300 => '#fca5a5', 400 => '#f87171', 500 => '#ef4444', 600 => '#dc2626', 700 => '#b91c1c', 800 => '#991b1b', 900 => '#7f1d1d', 950 => '#450a0a'],
        'orange' => [50 => '#fff7ed', 100 => '#ffedd5', 200 => '#fed7aa', 300 => '#fdba74', 400 => '#fb923c', 500 => '#f97316', 600 => '#ea580c', 700 => '#c2410c', 800 => '#9a3412', 900 => '#7c2d12', 950 => '#431407'],
        'amber' => [50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d', 400 => '#fbbf24', 500 => '#f59e0b', 600 => '#d97706', 700 => '#b45309', 800 => '#92400e', 900 => '#78350f', 950 => '#451a03'],
        'yellow' => [50 => '#fefce8', 100 => '#fef9c3', 200 => '#fef08a', 300 => '#fde047', 400 => '#facc15', 500 => '#eab308', 600 => '#ca8a04', 700 => '#a16207', 800 => '#854d0e', 900 => '#713f12', 950 => '#422006'],
        'lime' => [50 => '#f7fee7', 100 => '#ecfccb', 200 => '#d9f99d', 300 => '#bef264', 400 => '#a3e635', 500 => '#84cc16', 600 => '#65a30d', 700 => '#4d7c0f', 800 => '#3f6212', 900 => '#365314', 950 => '#1a2e05'],
        'green' => [50 => '#f0fdf4', 100 => '#dcfce7', 200 => '#bbf7d0', 300 => '#86efac', 400 => '#4ade80', 500 => '#22c55e', 600 => '#16a34a', 700 => '#15803d', 800 => '#166534', 900 => '#14532d', 950 => '#052e16'],
        'emerald' => [50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7', 400 => '#34d399', 500 => '#10b981', 600 => '#059669', 700 => '#047857', 800 => '#065f46', 900 => '#064e3b', 950 => '#022c22'],
        'teal' => [50 => '#f0fdfa', 100 => '#ccfbf1', 200 => '#99f6e4', 300 => '#5eead4', 400 => '#2dd4bf', 500 => '#14b8a6', 600 => '#0d9488', 700 => '#0f766e', 800 => '#115e59', 900 => '#134e4a', 950 => '#042f2e'],
        'cyan' => [50 => '#ecfeff', 100 => '#cffafe', 200 => '#a5f3fc', 300 => '#67e8f9', 400 => '#22d3ee', 500 => '#06b6d4', 600 => '#0891b2', 700 => '#0e7490', 800 => '#155e75', 900 => '#164e63', 950 => '#083344'],
        'sky' => [50 => '#f0f9ff', 100 => '#e0f2fe', 200 => '#bae6fd', 300 => '#7dd3fc', 400 => '#38bdf8', 500 => '#0ea5e9', 600 => '#0284c7', 700 => '#0369a1', 800 => '#075985', 900 => '#0c4a6e', 950 => '#082f49'],
        'blue' => [50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd', 400 => '#60a5fa', 500 => '#3b82f6', 600 => '#2563eb', 700 => '#1d4ed8', 800 => '#1e40af', 900 => '#1e3a8a', 950 => '#172554'],
        'indigo' => [50 => '#eef2ff', 100 => '#e0e7ff', 200 => '#c7d2fe', 300 => '#a5b4fc', 400 => '#818cf8', 500 => '#6366f1', 600 => '#4f46e5', 700 => '#4338ca', 800 => '#3730a3', 900 => '#312e81', 950 => '#1e1b4b'],
        'violet' => [50 => '#f5f3ff', 100 => '#ede9fe', 200 => '#ddd6fe', 300 => '#c4b5fd', 400 => '#a78bfa', 500 => '#8b5cf6', 600 => '#7c3aed', 700 => '#6d28d9', 800 => '#5b21b6', 900 => '#4c1d95', 950 => '#2e1065'],
        'purple' => [50 => '#faf5ff', 100 => '#f3e8ff', 200 => '#e9d5ff', 300 => '#d8b4fe', 400 => '#c084fc', 500 => '#a855f7', 600 => '#9333ea', 700 => '#7e22ce', 800 => '#6b21a8', 900 => '#581c87', 950 => '#3b0764'],
        'fuchsia' => [50 => '#fdf4ff', 100 => '#fae8ff', 200 => '#f5d0fe', 300 => '#f0abfc', 400 => '#e879f9', 500 => '#d946ef', 600 => '#c026d3', 700 => '#a21caf', 800 => '#86198f', 900 => '#701a75', 950 => '#4a044e'],
        'pink' => [50 => '#fdf2f8', 100 => '#fce7f3', 200 => '#fbcfe8', 300 => '#f9a8d4', 400 => '#f472b6', 500 => '#ec4899', 600 => '#db2777', 700 => '#be185d', 800 => '#9d174d', 900 => '#831843', 950 => '#500724'],
        'rose' => [50 => '#fff1f2', 100 => '#ffe4e6', 200 => '#fecdd3', 300 => '#fda4af', 400 => '#fb7185', 500 => '#f43f5e', 600 => '#e11d48', 700 => '#be123c', 800 => '#9f1239', 900 => '#881337', 950 => '#4c0519'],
    ];

    $palette = $palettes[$colorName] ?? $palettes['gray'];

    return $palette[$shade] ?? $palette[500];
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
        'color' => $existingSession?->getData()->color ?? 'blue',
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
            'color' => $data['color'],
            'day' => $data['day'],
            'slot' => $data['slot'],
            'exercises' => $data['exercises'],
        ]);
    } else {
        $this->dispatch('schedule-action', action: 'session.add', params: [
            'weekIndex' => $weekIndex,
            'name' => $data['name'],
            'color' => $data['color'],
            'day' => $data['day'],
            'slot' => $data['slot'],
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
                                        $sessionName = $session?->getData()->name;
                                        $sessionColor = $session?->getData()->color ?? 'blue';
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
                                        @if ($session)
                                            @php
                                                $bgColor = $this->getColorValue($sessionColor, 500);
                                                $lightBgColor = $this->getColorValue($sessionColor, 200);
                                                $borderColor = $this->getColorValue($sessionColor, 400);
                                                $isLinkedStyle = $week->isLinked() || $sessionIsLinked;
                                                $weekLinkInfo = $this->getWeekLinkInfo($week->uuid);
                                            @endphp
                                            <div class="h-full flex items-center justify-center gap-1 rounded px-2 py-1 transition-transform duration-200 cursor-move {{ $isLinkedStyle ? 'border-2 border-dashed' : '' }} cursor-pointer"
                                                :class="{
                                                    'opacity-50 scale-95': draggedSessionUuid === '{{ $session->uuid }}',
                                                    'cursor-not-allowed': isDraggingOver === '{{ $cellKey }}' && isDropDisallowed
                                                }"
                                                style="background-color: {{ $isLinkedStyle ? $lightBgColor : $bgColor }}; color: white; {{ $isLinkedStyle ? 'border-color: ' . $borderColor . ';' : '' }}"
                                                draggable="true"
                                                @dragstart="startDrag($event, '{{ $session->uuid }}', '{{ $sessionLinkedTo }}', {{ $day }}, {{ $slotIndex }})"
                                                @dragend="draggedSessionUuid = null; draggedSessionLinkedTo = null; isDraggingOver = null; isDropDisallowed = false"
                                                @dblclick="confirmUnlinkAndAction({{ Js::from($weekLinkInfo) }}, () => $wire.openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }}))">
                                                <span class="text-xs font-medium {{ $isLinkedStyle ? 'text-zinc-800' : '' }}" x-text="formatName('{{ $sessionName ?? 'Session' }}')"></span>
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
