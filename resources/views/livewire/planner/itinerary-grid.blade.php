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
    foreach ($this->block->children as $week) {
        if ($week->uuid === $weekUuid) {
            foreach ($week->children as $session) {
                if ($session->data->day === $day && $session->data->slot === $slot) {
                    return $session;
                }
            }
        }
    }
    return null;
};

$getItinerary = function (string $weekUuid, int $slot, int $day): ?int {
    $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
    return $session?->data->category;
};

$getSessionName = function (string $weekUuid, int $slot, int $day): ?string {
    $session = $this->getSessionAtPosition($weekUuid, $slot, $day);
    return $session?->data->name;
};

$getWeekIndex = function (string $weekUuid): int {
    foreach ($this->block->children as $index => $week) {
        if ($week->uuid === $weekUuid) {
            return $index;
        }
    }
    return 0;
};

$openSessionModal = function (string $weekUuid, int $slot, int $day) {
    $existingSession = $this->getSessionAtPosition($weekUuid, $slot, $day);

    $this->dispatch('open-session-modal', [
        'weekUuid' => $weekUuid,
        'day' => $day,
        'slot' => $slot,
        'sessionUuid' => $existingSession?->uuid,
        'name' => $existingSession?->data->name,
        'category' => $existingSession?->data->category,
        'exercises' => $existingSession?->data->exercises ?? [],
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

on(['session-deleted' => function (array $data) {
    $weekIndex = $this->getWeekIndex($data['weekUuid']);

    $this->dispatch('itinerary-action', action: 'session.delete', params: [
        'weekIndex' => $weekIndex,
        'slot' => $data['slot'],
        'day' => $data['day'],
    ]);
}]);

on(['grid-refresh' => function ($block) {
    $this->block = TrainingNode::from($block);
}]);

?>

<div x-data="{ show: false }" x-init="$nextTick(() => show = true)">
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
                    @foreach ($block->children as $weekIndex => $week)
                        @foreach ($this->slots as $slotIndex => $slotName)
                            <tr>
                                @if ($slotIndex === 0)
                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium bg-zinc-50 dark:bg-zinc-800/50"
                                        rowspan="2">
                                        Week {{ $weekIndex + 1 }}
                                    </td>
                                @endif
                                <td
                                    class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50">
                                    {{ $slotName }}
                                </td>
                                @for ($day = 0; $day < 7; $day++)
                                    @php
                                        $categoryId = $this->getItinerary($week->uuid, $slotIndex, $day);
                                        $category = $categoryId
                                            ? $this->categoriesById[$categoryId] ?? null
                                            : null;
                                        $sessionName = $this->getSessionName($week->uuid, $slotIndex, $day);
                                    @endphp
                                    <td class="border border-zinc-300 dark:border-zinc-600 p-1 h-12 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                        wire:click="openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }})">
                                        @if ($category)
                                            <div class="h-full flex items-center justify-center rounded px-2 py-1"
                                                style="background-color: {{ $this->getColorValue($category->background_color) }}; color: {{ $this->getColorValue($category->text_color) }};">
                                                <span class="text-xs font-medium">{{ $sessionName ?? $category->name }}</span>
                                            </div>
                                        @else
                                            <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
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
</div>
