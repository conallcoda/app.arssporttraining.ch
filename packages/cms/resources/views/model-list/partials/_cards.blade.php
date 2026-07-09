@php
    $layout = $this->cardLayout;
    $cardWidth = $this->cardWidth;
    $cardMinWidth = $this->cardMinWidth;
    $cardItemClass = $this->cardItemClass;
    $cardDefinition = $this->cardDefinition;
    $cardLightbox = $this->cardLightbox;
    $manualSortingEnabled = $this->manualSortingEnabled();
    $gridXData = $manualSortingEnabled
        ? 'cms_sort_group({ method: \'reorderCurrentPage\', grouped: false })'
        : '{}';
    $gridXSort = $manualSortingEnabled ? 'handleSort($item, $position)' : null;
    $gridXSortConfig = $manualSortingEnabled ? '{ forceFallback: true, fallbackOnBody: true }' : '{}';
    $containerClass = $cardItemClass
        ? 'flex flex-wrap gap-4 items-start'
        : 'grid gap-4';
    $containerStyle = $cardItemClass
        ? ''
        : 'grid-template-columns: repeat(auto-fill, minmax('.$cardMinWidth.', 1fr));';
@endphp

@if ($layout === 'masonry')
    @include('cms::model-list.partials._cards-masonry', [
        'cardDefinition' => $cardDefinition,
        'cardWidth' => $cardWidth,
    ])

    @include('cms::model-list.partials._lightbox', [
        'titleField' => $cardDefinition?->titleField,
        'bodyFields' => $cardDefinition?->bodyFields ?? [],
        'imageField' => $cardDefinition?->imageField,
        'alternateImageField' => $cardDefinition?->alternateImageField,
    ])
@else
    @if ($manualSortingEnabled)
        <div
            class="{{ $containerClass }}"
            style="{{ $containerStyle }}"
            x-data="{{ $gridXData }}"
            x-sort:config="{{ $gridXSortConfig }}"
            x-sort="{{ $gridXSort }}"
        >
    @else
        <div
            class="{{ $containerClass }}"
            style="{{ $containerStyle }}"
        >
    @endif
        @foreach ($this->items as $model)
            @php
                $item = $this->dataFromModel($model);
                $cardSortKey = $manualSortingEnabled ? (string) $item->id : null;
                $cardUrl = $this->cardUrl($item, $model);
            @endphp

            @if ($manualSortingEnabled)
                <div x-sort:item="{{ $cardSortKey }}" data-sort-item-key="{{ $cardSortKey }}" class="{{ $cardItemClass }}">
            @else
                <div class="{{ $cardItemClass }}">
            @endif
                @include('cms::partials.card-content', [
                    'definition' => $cardDefinition,
                    'record' => $item,
                    'item' => $item,
                    'model' => $model,
                    'cards' => $this->cards,
                    'cardUrl' => $cardUrl,
                    'lightboxEnabled' => $cardLightbox,
                    'lightboxIndex' => $loop->index,
                    'wireKey' => 'card-'.$item->id.'-'.$this->refreshKey,
                    'footerView' => 'cms::model-list.partials._card-footer',
                    'footerData' => ['item' => $item, 'model' => $model],
                    'bodyClass' => 'p-4 flex-1 flex flex-col gap-3',
                ])

                @if ($manualSortingEnabled)
                    <div class="mt-2 flex justify-end px-1">
                        <button
                            type="button"
                            x-sort:handle
                            class="flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 cursor-grab active:cursor-grabbing"
                            aria-label="Drag to reorder"
                        >
                            <flux:icon.grip class="size-4" />
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($cardLightbox)
        @include('cms::model-list.partials._lightbox', [
            'titleField' => $cardDefinition?->titleField,
            'bodyFields' => $cardDefinition?->bodyFields ?? [],
            'imageField' => $cardDefinition?->imageField,
            'alternateImageField' => $cardDefinition?->alternateImageField,
        ])
    @endif
@endif

<div class="[&_[data-flux-pagination]]:!border-t-0">
    @include('cms::model-list.partials._pagination')
</div>
