@if ($this->items->hasMorePages())
    @if ($loadedPages === 1)
        <div class="mt-4 flex justify-center">
            <flux:button variant="outline" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                <span wire:loading.remove wire:target="loadMore">{{ __('Load more') }}</span>
                <span wire:loading wire:target="loadMore" class="flex items-center gap-2">
                    <flux:icon.loading class="size-4 animate-spin" />
                    {{ __('Loading...') }}
                </span>
            </flux:button>
        </div>
    @else
        <div wire:key="sentinel-{{ $loadedPages }}"
             x-data="{
                loading: false,
                loadMore() {
                    if (this.loading) return;

                    this.loading = true;
                    const request = $wire.loadMore();

                    if (request && typeof request.finally === 'function') {
                        request.finally(() => this.loading = false);
                    } else {
                        this.loading = false;
                    }
                },
             }"
             x-init="
                const observer = new IntersectionObserver((entries) => {
                    if (entries.some(entry => entry.isIntersecting)) {
                        loadMore();
                    }
                }, { rootMargin: '600px 0px' });

                observer.observe($el);
             "
             class="flex mt-4 py-4 items-center justify-center gap-2 text-zinc-400 dark:text-zinc-500 text-sm">
            <flux:icon.loading class="size-4 animate-spin" />
            {{ __('Loading more...') }}
        </div>
    @endif
@endif
