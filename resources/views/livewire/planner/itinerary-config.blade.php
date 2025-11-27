<?php

use App\Livewire\ItineraryConfig;
use function Livewire\Volt\{state, mount, rules, updated};

state(ItineraryConfig::empty());

rules([
    'numberOfBlocks' => 'required|integer|min:1',
    'weeksPerBlock' => 'required|integer|min:1',
]);

$dispatchState = function () {
    $this->dispatch('itinerary-config-changed', config: ItineraryConfig::from([
        'numberOfBlocks' => $this->numberOfBlocks,
        'weeksPerBlock' => $this->weeksPerBlock,
    ]));
};

updated([
    'numberOfBlocks' => fn () => $this->validate() && $this->dispatchState(),
    'weeksPerBlock' => fn () => $this->validate() && $this->dispatchState(),
]);

mount(fn () => $this->dispatchState());

?>

<flux:card wire:key="itinerary-config-card">
    <flux:heading size="lg">Config</flux:heading>
    <div class="mt-6 space-y-4">
        <flux:input wire:key="input-blocks" wire:model.live="numberOfBlocks" label="Number of Blocks" type="number" min="1" max="12" />
        <flux:input wire:key="input-weeks" wire:model.live="weeksPerBlock" label="Weeks per Block" type="number" min="1" max="12" />
    </div>
</flux:card>
