@props([
    'content' => null,
    'position' => 'bottom',
    'align' => 'center',
    'label' => 'Show help',
])

<flux:tooltip :content="$content" :position="$position" :align="$align" class="contents">
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <button
            type="button"
            aria-label="{{ $label }}"
            class="inline-flex items-center justify-center text-zinc-400 transition-colors hover:text-zinc-600 focus:outline-none focus:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 dark:focus:text-zinc-300"
        >
            <flux:icon.circle-question-mark class="size-4" />
        </button>
    @endif
</flux:tooltip>
