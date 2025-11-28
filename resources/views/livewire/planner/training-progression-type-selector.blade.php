<?php

use function Livewire\Volt\{state, mount, computed};

state([
    'progressionType' => null,
]);

$types = computed(fn () => []);

mount(function () {
    $this->progressionType = request()->cookie('progression-type');
});

$setProgressionType = function (string $type) {
    $this->progressionType = $type;
    $this->dispatch('progression-type-changed', type: $type);
};

?>

<div
    x-data="{ type: @entangle('progressionType') }"
    x-init="$watch('type', value => {
        if (value) {
            document.cookie = 'progression-type=' + value + '; path=/; max-age=31536000';
        }
    })"
>
    <flux:dropdown>
        <flux:button size="sm" variant="ghost" disabled>
            No progression rules
            <x-lucide-chevron-down class="w-3 h-3 ml-1" />
        </flux:button>
        <flux:menu>
            @foreach ($this->types as $key => $config)
                <flux:menu.item
                    wire:click="setProgressionType('{{ $key }}')"
                    :class="$progressionType === $key ? 'bg-zinc-100 dark:bg-zinc-800' : ''"
                >
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $config['bg'] }};"></span>
                        {{ $config['label'] }}
                    </span>
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
