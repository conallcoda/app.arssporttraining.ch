@php
    $lightboxImageUrls = [];
    foreach ($this->items as $lightboxModel) {
        $lightboxItem = $this->dataFromModel($lightboxModel);
        $lightboxImageUrls[] = $imageField ? ($lightboxItem->{$imageField->field} ?? null) : null;
    }
    $lightboxCount = count($lightboxImageUrls);
@endphp

<div x-data="cmsLightbox({
        imageUrls: {{ Js::from($lightboxImageUrls) }},
        count: {{ $lightboxCount }},
    })"
    x-on:open-lightbox.window="open($event.detail.index)"
    x-on:keydown.escape.window="close()"
    x-on:keydown.left.window="if (isOpen) prev()"
    x-on:keydown.right.window="if (isOpen) next()"
    x-show="isOpen"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-40 bg-black/90"
    x-on:click="close()">

    <button type="button"
        x-on:click.stop="close()"
        class="absolute top-4 right-4 z-[2] text-white/70 hover:text-white p-2 cursor-pointer">
        <flux:icon.x class="size-6" />
    </button>

    <button type="button"
        x-show="count > 1"
        x-on:click.stop="prev()"
        class="absolute left-4 top-1/2 -translate-y-1/2 z-[2] text-white/70 hover:text-white p-2 cursor-pointer">
        <flux:icon.chevron-left class="size-10" />
    </button>

    <button type="button"
        x-show="count > 1"
        x-on:click.stop="next()"
        class="absolute right-4 top-1/2 -translate-y-1/2 z-[2] text-white/70 hover:text-white p-2 cursor-pointer">
        <flux:icon.chevron-right class="size-10" />
    </button>

    <div class="absolute inset-0 flex items-center justify-center p-12 pointer-events-none">
        <div class="relative max-w-[90vw] max-h-[90vh] pointer-events-auto" x-on:click.stop>
            <img :src="imageUrls[currentIndex]" alt=""
                class="block max-w-[90vw] max-h-[90vh] object-contain rounded" />

            @foreach ($this->items as $lightboxIdx => $lightboxModel)
                @php
                    $lightboxItem = $this->dataFromModel($lightboxModel);
                @endphp
                <div x-show="currentIndex === {{ $lightboxIdx }}" x-cloak>
                    <div class="absolute bottom-0 inset-x-0 px-6 py-4 bg-gradient-to-t from-black/80 via-black/40 to-transparent pointer-events-none rounded-b">
                        @if ($titleField)
                            <div class="text-white text-xl font-medium drop-shadow-sm">
                                @include('cms::model-list.partials._field-value', ['column' => $titleField, 'item' => $lightboxItem, 'model' => $lightboxModel])
                            </div>
                        @endif
                        @foreach ($bodyFields as $column)
                            <div class="text-white/80 text-sm drop-shadow-sm">
                                @include('cms::model-list.partials._field-value', ['column' => $column, 'item' => $lightboxItem, 'model' => $lightboxModel])
                            </div>
                        @endforeach
                    </div>

                    @if (count($this->rowActions) > 0 || count($this->rowMenuActions) > 0)
                        <div class="absolute top-3 right-3 flex gap-1">
                            @foreach ($this->rowActions as $action)
                                @if (! $action->isVisible($lightboxItem))
                                    @continue
                                @endif

                                @if ($action->isFormModal() && $action->name === 'edit')
                                    <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                        wire:click="startEdit({{ $lightboxItem->id }})" />
                                @elseif ($action->isFormModal())
                                    <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                        x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                                            data: {{ Js::from($lightboxItem->toArray()) }},
                                            title: '{{ $action->modalTitle }}'
                                        })" />
                                @elseif ($action->isConfirm())
                                    <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                        wire:click="confirmAction('{{ $action->name }}', {{ $lightboxItem->id }})"
                                        class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                @elseif ($action->isAlpineEvent())
                                    <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                        x-on:click="$dispatch('{{ $action->alpineEventName }}', {{ Js::from($action->getAlpineEventData($lightboxItem)) }})" />
                                @elseif ($action->isDirect())
                                    <flux:button variant="filled" size="xs" icon="{{ $action->icon }}"
                                        wire:click="{{ $action->getHandler() }}({{ $lightboxItem->id }})" />
                                @endif
                            @endforeach

                            @if (count($this->rowMenuActions) > 0)
                                <flux:dropdown>
                                    <flux:button variant="filled" size="xs" icon="ellipsis" />
                                    <flux:menu>
                                        @foreach ($this->rowMenuActions as $menuAction)
                                            @if ($menuAction->isFormModal())
                                                <flux:menu.item :icon="$menuAction->icon"
                                                    wire:click="openActionModal('{{ $menuAction->name }}', {{ $lightboxItem->id }})">
                                                    {{ $menuAction->label }}</flux:menu.item>
                                            @elseif ($menuAction->isConfirm())
                                                <flux:menu.item :icon="$menuAction->icon"
                                                    wire:click="confirmAction('{{ $menuAction->name }}', {{ $lightboxItem->id }})">
                                                    {{ $menuAction->label }}</flux:menu.item>
                                            @elseif ($menuAction->isAlpineEvent())
                                                <flux:menu.item :icon="$menuAction->icon"
                                                    x-on:click="$dispatch('{{ $menuAction->alpineEventName }}', {{ Js::from($menuAction->getAlpineEventData($lightboxItem)) }})">
                                                    {{ $menuAction->label }}</flux:menu.item>
                                            @else
                                                <flux:menu.item :icon="$menuAction->icon"
                                                    wire:click="{{ $menuAction->getHandler() }}({{ $lightboxItem->id }})">
                                                    {{ $menuAction->label }}</flux:menu.item>
                                            @endif
                                        @endforeach
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
