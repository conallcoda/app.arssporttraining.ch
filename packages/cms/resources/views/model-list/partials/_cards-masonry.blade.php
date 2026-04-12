@php
    $overlayViewOverride = $this->masonryOverlayView;
    $cardViewOverride = $this->cardView;
@endphp

<div class="[column-width:var(--cms-card-width,220px)] [column-gap:4px] supports-[grid-template-rows:masonry]:grid supports-[grid-template-rows:masonry]:grid-cols-[repeat(auto-fill,minmax(var(--cms-card-width,220px),1fr))] supports-[grid-template-rows:masonry]:[grid-template-rows:masonry] supports-[grid-template-rows:masonry]:gap-1"
     style="--cms-card-width: {{ $cardWidth }}px;">
    @foreach ($this->items as $model)
        @php
            $item = $this->dataFromModel($model);
            $imageUrl = $imageField ? ($item->{$imageField->field} ?? null) : null;
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
                 class="break-inside-avoid mb-1 block supports-[grid-template-rows:masonry]:mb-0 group relative overflow-hidden">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="" class="w-full h-auto block" loading="lazy" />
                @else
                    <div class="w-full aspect-square bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                        <flux:icon.image class="size-10" />
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

                @if (count($this->rowActions) > 0 || count($this->rowMenuActions) > 0)
                    <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity flex gap-0.5">
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
