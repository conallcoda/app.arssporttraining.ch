<?php

use App\Models\Training\TrainingNode;
use function Livewire\Volt\{state, computed, on};

state([
    'showWeekModal' => false,
    'weekUuid' => null,
    'weekIndex' => 0,
    'blockIndex' => 0,
    'linkedTo' => null,
    'availableWeeks' => [],
]);

on([
    'open-week-modal' => function (array $data) {
        $this->weekUuid = $data['weekUuid'];
        $this->weekIndex = $data['weekIndex'];
        $this->blockIndex = $data['blockIndex'];
        $this->linkedTo = $data['linkedTo'] ?? null;
        $this->availableWeeks = $data['availableWeeks'] ?? [];
        $this->showWeekModal = true;
    },
]);

$close = function () {
    $this->showWeekModal = false;
    $this->weekUuid = null;
    $this->weekIndex = 0;
    $this->blockIndex = 0;
    $this->linkedTo = null;
    $this->availableWeeks = [];
};

$isFirstWeek = computed(fn() => $this->blockIndex === 0 && $this->weekIndex === 0);

$save = function () {
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

?>

<div>
    <flux:modal name="week-modal" wire:model="showWeekModal" variant="flyout" class="w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    Week {{ $weekIndex + 1 }} Settings
                </flux:heading>
                <flux:subheading>
                    Block {{ $blockIndex + 1 }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>Link to Week</flux:label>
                <select
                    wire:model="linkedTo"
                    class="w-full rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm"
                    {{ $this->isFirstWeek ? 'disabled' : '' }}
                >
                    <option value="">No link (independent week)</option>
                    @foreach ($availableWeeks as $week)
                        <option value="{{ $week['uuid'] }}">
                            Block {{ $week['blockIndex'] + 1 }} Week {{ $week['weekIndex'] + 1 }}
                        </option>
                    @endforeach
                </select>
                @if ($this->isFirstWeek)
                    <flux:description>
                        The first week of the first block cannot be linked to another week as it serves as the source for all other weeks.
                    </flux:description>
                @else
                    <flux:description>
                        Linking this week will make it inherit all sessions from the selected week.
                    </flux:description>
                @endif
            </flux:field>

            <div class="flex gap-2 justify-end pt-4">
                <flux:button type="button" variant="ghost" wire:click="close">Cancel</flux:button>
                @if (!$this->isFirstWeek)
                    <flux:button type="submit" variant="primary">Save</flux:button>
                @endif
            </div>
        </form>
    </flux:modal>
</div>
