@props([
    'title' => null,
    'description' => null,
    'showClose' => true,
    'divider' => false,
    'padding' => null,
])

<div @class([
    'mb-6 flex items-center justify-between gap-4',
    'border-b border-zinc-200 dark:border-zinc-700' => $divider,
    $padding,
])>
    <div class="min-w-0 flex-1">
        @if ($title)
            <div class="text-base font-medium text-zinc-900 dark:text-zinc-100">
                {{ $title }}
            </div>
        @endif

        @if ($description)
            <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $description }}
            </div>
        @endif
    </div>

    @if ($showClose)
        <flux:modal.close>
            <flux:button
                variant="ghost"
                icon="x-mark"
                size="sm"
                aria-label="Close modal"
                class="shrink-0 text-zinc-400! hover:text-zinc-800! dark:text-zinc-500! dark:hover:text-white!"
            />
        </flux:modal.close>
    @endif
</div>
