@if ($this->items->hasMorePages())
    <div wire:key="sentinel-{{ $loadedPages }}"
         x-intersect.once="$wire.loadMore()"
         class="mt-4 py-4 flex items-center justify-center gap-2 text-zinc-400 dark:text-zinc-500 text-sm">
        <flux:icon.loading class="size-4 animate-spin" />
        {{ __('Loading more...') }}
    </div>
@endif
