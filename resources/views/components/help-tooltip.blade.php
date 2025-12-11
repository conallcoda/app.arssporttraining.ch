@props(['content' => null, 'position' => 'left'])

<flux:dropdown :position="$position">
    <button type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 cursor-pointer">
        <flux:icon.info variant="micro" />
    </button>
    <flux:popover class="max-w-xs">
        @if ($slot->isNotEmpty())
            <div class="text-sm">{{ $slot }}</div>
        @else
            <p class="text-sm">{{ $content }}</p>
        @endif
    </flux:popover>
</flux:dropdown>
