@if ($this->items->hasMorePages())
    <div class="mt-4 flex justify-center">
        <flux:button variant="outline" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
            <span wire:loading.remove wire:target="loadMore">{{ __('Load more') }}</span>
            <span wire:loading wire:target="loadMore" class="flex items-center gap-2">
                <flux:icon.loader class="size-4 animate-spin" />
                {{ __('Loading...') }}
            </span>
        </flux:button>
    </div>
@endif
