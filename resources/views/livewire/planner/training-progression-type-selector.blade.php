<?php

use function Livewire\Volt\{state, mount, computed, on};

state([
    'weightStrategy' => 'fixed_step',
    'repStrategy' => 'paired_ladder',
]);

$weightStrategies = computed(fn () => [
    'fixed_step' => [
        'label' => 'Fixed Step',
        'description' => 'Weight increases by fixed steps working backwards from target',
        'color' => 'sky',
    ],
    'compounded' => [
        'label' => 'Compounded',
        'description' => 'Weight compounds weekly to reach target improvement',
        'color' => 'sky',
    ],
]);

$repStrategies = computed(fn () => [
    'paired_ladder' => [
        'label' => 'Paired Ladder',
        'description' => 'Sets in pairs with decreasing reps over the block',
        'color' => 'amber',
    ],
    'fixed' => [
        'label' => 'Fixed',
        'description' => 'Same rep count for all sets',
        'color' => 'amber',
    ],
]);

mount(function () {
    $this->weightStrategy = request()->cookie('weight-strategy', 'fixed_step');
    $this->repStrategy = request()->cookie('rep-strategy', 'paired_ladder');
});

$setWeightStrategy = function (string $strategy) {
    $this->weightStrategy = $strategy;
    $this->dispatch('progression-config-changed', config: [
        'weightStrategy' => $strategy,
    ]);
};

$setRepStrategy = function (string $strategy) {
    $this->repStrategy = $strategy;
    $this->dispatch('progression-config-changed', config: [
        'repStrategy' => $strategy,
    ]);
};

on([
    'grid-refresh' => function ($block, $progressionConfig = null, $overrideStore = null) {
        if ($progressionConfig) {
            $weightConfig = $progressionConfig['weightConfig'] ?? null;
            $repConfig = $progressionConfig['repConfig'] ?? null;

            if ($weightConfig) {
                $weightClass = $weightConfig['class'] ?? ($weightConfig['type'] ?? null);
                $this->weightStrategy = match (true) {
                    str_contains($weightClass ?? '', 'Compounded') => 'compounded',
                    default => 'fixed_step',
                };
            }

            if ($repConfig) {
                $repType = $repConfig['type'] ?? null;
                $this->repStrategy = match ($repType) {
                    'fixed' => 'fixed',
                    default => 'paired_ladder',
                };
            }
        }
    },
]);

?>

<div
    x-data="{
        weightStrategy: @entangle('weightStrategy'),
        repStrategy: @entangle('repStrategy')
    }"
    x-init="
        $watch('weightStrategy', value => {
            if (value) document.cookie = 'weight-strategy=' + value + '; path=/; max-age=31536000';
        });
        $watch('repStrategy', value => {
            if (value) document.cookie = 'rep-strategy=' + value + '; path=/; max-age=31536000';
        });
    "
    class="flex items-center gap-2"
>
    @php
        $activeWeight = $this->weightStrategies[$weightStrategy] ?? null;
        $activeRep = $this->repStrategies[$repStrategy] ?? null;
    @endphp

    <flux:dropdown>
        <flux:button size="sm" class="bg-sky-100 text-sky-700 hover:bg-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:hover:bg-sky-900/50 border-sky-200 dark:border-sky-800">
            Weight: {{ $activeWeight['label'] ?? 'Select' }}
            <x-lucide-chevron-down class="w-3 h-3 ml-1" />
        </flux:button>
        <flux:menu>
            @foreach ($this->weightStrategies as $key => $config)
                <flux:menu.item
                    wire:click="setWeightStrategy('{{ $key }}')"
                    :class="$weightStrategy === $key ? 'bg-sky-50 dark:bg-sky-900/20' : ''"
                >
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $config['label'] }}</span>
                            <span class="text-xs text-zinc-500">{{ $config['description'] }}</span>
                        </div>
                    </div>
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>

    <flux:dropdown>
        <flux:button size="sm" class="bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50 border-amber-200 dark:border-amber-800">
            Reps: {{ $activeRep['label'] ?? 'Select' }}
            <x-lucide-chevron-down class="w-3 h-3 ml-1" />
        </flux:button>
        <flux:menu>
            @foreach ($this->repStrategies as $key => $config)
                <flux:menu.item
                    wire:click="setRepStrategy('{{ $key }}')"
                    :class="$repStrategy === $key ? 'bg-amber-50 dark:bg-amber-900/20' : ''"
                >
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $config['label'] }}</span>
                            <span class="text-xs text-zinc-500">{{ $config['description'] }}</span>
                        </div>
                    </div>
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
