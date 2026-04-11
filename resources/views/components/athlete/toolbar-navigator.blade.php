@props([
    'previousHref',
    'nextHref',
])

<div class="flex items-center bg-zinc-800/5 p-0 sm:rounded-xl dark:bg-white/10">
    <a
        href="{{ $previousHref }}"
        wire:navigate
        class="inline-flex size-11 shrink-0 items-center justify-center text-zinc-700 transition hover:bg-white dark:text-white dark:hover:bg-white/20"
        aria-label="Previous"
    >
        <flux:icon.chevron-left class="size-4" />
    </a>

    <div class="min-w-0 flex-1 px-0">
        {{ $slot }}
    </div>

    <a
        href="{{ $nextHref }}"
        wire:navigate
        class="inline-flex size-11 shrink-0 items-center justify-center text-zinc-700 transition hover:bg-white dark:text-white dark:hover:bg-white/20"
        aria-label="Next"
    >
        <flux:icon.chevron-right class="size-4" />
    </a>
</div>
