@props([
    'title' => null,
    'storageKey' => null,
])

@php
    $config = [
        'storageKey' => $storageKey,
    ];
@endphp

<div x-data="resizable_card"
     data-config="{{ base64_encode(json_encode($config)) }}"
     class="transition-all duration-300 ease-in-out"
     :class="expanded ? 'col-span-12' : 'col-span-6'">
    <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
            @if($title)
                <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $title }}</h3>
            @else
                <div></div>
            @endif
            <button @click="toggle()"
                    class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
                    :title="expanded ? 'Minimize' : 'Maximize'">
                <template x-if="expanded">
                    <x-lucide-minimize-2 class="w-4 h-4" />
                </template>
                <template x-if="!expanded">
                    <x-lucide-maximize class="w-4 h-4" />
                </template>
            </button>
        </div>
        <div class="p-4">
            {{ $slot }}
        </div>
    </div>
</div>
