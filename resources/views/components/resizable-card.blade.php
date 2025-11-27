@props([
    'title' => null,
    'storageKey' => null,
    'titleActions' => null,
])

@php
    $config = [
        'storageKey' => $storageKey,
    ];
@endphp

<div x-data="resizable_card"
     data-config="{{ base64_encode(json_encode($config)) }}"
     class="transition-all duration-300 ease-in-out"
     :class="getColSpan()">
    <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
            <div class="flex items-center gap-3">
                @if($title)
                    <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $title }}</h3>
                @endif
                {{ $titleActions }}
            </div>
            <div class="flex items-center gap-1">
                <span class="text-xs text-zinc-400" x-text="width + '%'"></span>
                <flux:dropdown>
                    <flux:button variant="ghost" size="sm">
                        <x-lucide-scaling class="w-4 h-4" />
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item @click="setWidth(25)" x-bind:class="width === 25 && 'bg-zinc-100 dark:bg-zinc-800'">25%</flux:menu.item>
                        <flux:menu.item @click="setWidth(33)" x-bind:class="width === 33 && 'bg-zinc-100 dark:bg-zinc-800'">33%</flux:menu.item>
                        <flux:menu.item @click="setWidth(50)" x-bind:class="width === 50 && 'bg-zinc-100 dark:bg-zinc-800'">50%</flux:menu.item>
                        <flux:menu.item @click="setWidth(66)" x-bind:class="width === 66 && 'bg-zinc-100 dark:bg-zinc-800'">66%</flux:menu.item>
                        <flux:menu.item @click="setWidth(75)" x-bind:class="width === 75 && 'bg-zinc-100 dark:bg-zinc-800'">75%</flux:menu.item>
                        <flux:menu.item @click="setWidth(100)" x-bind:class="width === 100 && 'bg-zinc-100 dark:bg-zinc-800'">100%</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
        <div class="p-4">
            {{ $slot }}
        </div>
    </div>
</div>
