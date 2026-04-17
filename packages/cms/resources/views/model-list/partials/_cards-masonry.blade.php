@php
    use Coda\Cms\Display\DisplayFields\View as ViewColumn;

    $overlayViewOverride = $this->masonryOverlayView;
    $cardViewOverride = $this->cardView;
@endphp

<div x-data="cmsMasonry({{ (int) $cardWidth }}, 4)"
     wire:ignore.self
     class="w-full relative">
    @foreach ($this->items as $model)
        @php
            $item = $this->dataFromModel($model);
            $imageUrl = $imageField ? ($item->{$imageField->field} ?? null) : null;

            $titleLinkUrl = null;
            $titleModalField = null;
            if ($titleField instanceof ViewColumn && $titleField->getViewRouteName()) {
                $titleLinkUrl = route($titleField->getViewRouteName(), $model);
            } elseif ($titleField && property_exists($titleField, 'modalField') && $titleField->modalField) {
                $titleModalField = $titleField->modalField;
            }
        @endphp

        @if ($cardViewOverride)
            @include($cardViewOverride, [
                'item' => $item,
                'model' => $model,
                'titleField' => $titleField,
                'bodyFields' => $bodyFields,
                'imageField' => $imageField,
                'cards' => $this->cards,
            ])
        @else
            <div wire:key="masonry-{{ $item->id }}-{{ $this->refreshKey }}"
                 wire:ignore.self
                 data-masonry-item
                 class="group absolute top-0 left-0 overflow-hidden">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="" class="w-full h-auto block" loading="lazy" />
                @else
                    <div class="w-full aspect-square bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                        <flux:icon.photo class="size-10" />
                    </div>
                @endif

                @if ($overlayViewOverride)
                    @include($overlayViewOverride, [
                        'item' => $item,
                        'model' => $model,
                        'titleField' => $titleField,
                        'bodyFields' => $bodyFields,
                    ])
                @else
                    <div class="absolute bottom-0 inset-x-0 px-3 py-2 bg-gradient-to-t from-black/70 via-black/40 to-transparent pointer-events-none">
                        @if ($titleField)
                            <div class="text-white text-sm font-medium drop-shadow-sm truncate">
                                @include('cms::model-list.partials._field-value', ['column' => $titleField, 'item' => $item, 'model' => $model])
                            </div>
                        @endif
                        @foreach ($bodyFields as $column)
                            <div class="text-white/80 text-xs drop-shadow-sm truncate">
                                @include('cms::model-list.partials._field-value', ['column' => $column, 'item' => $item, 'model' => $model])
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($titleLinkUrl)
                    <a href="{{ $titleLinkUrl }}"
                       aria-label="{{ $item->{$titleField->field} ?? '' }}"
                       class="absolute inset-0 z-[1]"></a>
                @elseif ($titleModalField)
                    <button type="button"
                        class="absolute inset-0 z-[1] cursor-pointer bg-transparent border-0 p-0 m-0 appearance-none"
                        aria-label="{{ $item->{$titleField->field} ?? '' }}"
                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                            data: {{ Js::from($item->toArray()) }},
                            title: 'Edit {{ $entityName }}',
                            focusField: '{{ $titleModalField }}'
                        })"></button>
                @endif

                @if ($imageUrl || count($this->rowActions) > 0 || count($this->rowMenuActions) > 0)
                    <div class="absolute top-1 right-1 z-[2] opacity-0 group-hover:opacity-100 transition-opacity flex gap-0.5">
                        @if ($imageUrl)
                            <flux:button variant="filled" size="xs" icon="eye"
                                x-on:click="$dispatch('open-lightbox', { index: {{ $loop->index }} })" />
                        @endif

                        @foreach ($this->rowActions as $action)
                            @if (! $action->isVisible($item))
                                @continue
                            @endif

                            @if ($action->isFormModal() && $action->name === 'edit')
                                <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                    wire:click="startEdit({{ $item->id }})" />
                            @elseif ($action->isFormModal())
                                <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                    x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                                        data: {{ Js::from($item->toArray()) }},
                                        title: '{{ $action->modalTitle }}'
                                    })" />
                            @elseif ($action->isConfirm())
                                <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                    wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                                    class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                            @elseif ($action->isAlpineEvent())
                                <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                    x-on:click="$dispatch('{{ $action->alpineEventName }}', {{ Js::from($action->getAlpineEventData($item)) }})" />
                            @elseif ($action->isDirect())
                                <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                    wire:click="{{ $action->getHandler() }}({{ $item->id }})" />
                            @endif
                        @endforeach

                        @if (count($this->rowMenuActions) > 0)
                            <flux:dropdown>
                                <flux:button variant="filled" size="xs" icon="ellipsis" />
                                <flux:menu>
                                    @foreach ($this->rowMenuActions as $menuAction)
                                        <flux:menu.item :icon="$menuAction->icon"
                                            wire:click="openActionModal('{{ $menuAction->name }}', {{ $item->id }})">
                                            {{ $menuAction->label }}</flux:menu.item>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    @endforeach
</div>
