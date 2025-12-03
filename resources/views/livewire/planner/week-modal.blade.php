<?php

use App\Models\Training\TrainingNode;
use function Livewire\Volt\{state, computed, on};

state([
    'showWeekModal' => false,
    'weekUuid' => null,
    'weekIndex' => 0,
    'linkedTo' => null,
    'availableWeeks' => [],
    'isAddMode' => false,
]);

on([
    'open-week-modal' => function (array $data) {
        $this->weekUuid = $data['weekUuid'];
        $this->weekIndex = $data['weekIndex'];
        $this->linkedTo = $data['linkedTo'] ?? null;
        $this->availableWeeks = $data['availableWeeks'] ?? [];
        $this->isAddMode = $data['isAddMode'] ?? false;
        $this->showWeekModal = true;
    },
]);

$close = function () {
    $this->reset([
        'showWeekModal',
        'weekUuid',
        'weekIndex',
        'linkedTo',
        'availableWeeks',
        'isAddMode',
    ]);
};

$isFirstWeek = computed(fn() => $this->weekIndex === 0);

$save = function () {
    if ($this->isAddMode) {
        $this->dispatch('week-added', [
            'linkedTo' => $this->linkedTo,
        ]);
        $this->close();
        return;
    }

    if ($this->isFirstWeek) {
        $this->close();
        return;
    }

    $this->dispatch('week-linked', [
        'weekUuid' => $this->weekUuid,
        'weekIndex' => $this->weekIndex,
        'linkedTo' => $this->linkedTo,
    ]);

    $this->close();
};

$delete = function () {
    $this->dispatch('week-deleted', [
        'weekUuid' => $this->weekUuid,
        'weekIndex' => $this->weekIndex,
    ]);

    $this->close();
};

?>

<div>
    <flux:modal name="week-modal" wire:model="showWeekModal" variant="flyout" class="w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($isAddMode)
                        Add Week {{ $weekIndex + 1 }}
                    @else
                        Week {{ $weekIndex + 1 }} Settings
                    @endif
                </flux:heading>
            </div>

            <flux:field>
                <flux:label>Linked To</flux:label>
                <select
                    wire:model.live="linkedTo"
                    class="w-full rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm"
                    {{ !$isAddMode && $this->isFirstWeek ? 'disabled' : '' }}
                >
                    <option value="" @selected($linkedTo === null || $linkedTo === '')>Unlinked</option>
                    @foreach ($availableWeeks ?? [] as $week)
                        <option value="{{ $week['uuid'] }}" @selected($linkedTo === $week['uuid'])>
                            Week {{ $week['weekIndex'] + 1 }}
                        </option>
                    @endforeach
                </select>
                @if (!$isAddMode && $this->isFirstWeek)
                    <flux:description>
                        The first week cannot be linked to another week as it serves as the source for all other weeks.
                    </flux:description>
                @elseif ($isAddMode)
                    <flux:description>
                        Optionally link this week to inherit all sessions from an existing week.
                    </flux:description>
                @else
                    <flux:description>
                        Linking this week will make it inherit all sessions from the selected week.
                    </flux:description>
                @endif
            </flux:field>

            <div class="flex gap-2 justify-between pt-4">
                <div>
                    @if (!$isAddMode && !$this->isFirstWeek)
                        <flux:button type="button" variant="danger" size="sm" wire:click="delete"
                            wire:confirm="Are you sure you want to delete this week?">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                    @if ($isAddMode || !$this->isFirstWeek)
                        <flux:button type="submit" variant="primary">
                            {{ $isAddMode ? 'Add Week' : 'Save' }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </form>
    </flux:modal>
</div>
