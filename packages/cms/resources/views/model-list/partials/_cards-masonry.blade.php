@php
    use Coda\Cms\Display\CardField;

    $overlayViewOverride = $this->masonryOverlayView;
    $definition = $cardDefinition ?? $this->cardDefinition;
    $titleField = $definition?->titleField;
    $imageField = $definition?->imageField;
    $overlayFields = collect([
        $definition?->subtitleField,
        ...($definition?->metaFields ?? []),
        ...($definition?->bodyFields ?? []),
    ])->filter(fn (mixed $field) => $field instanceof CardField)->values();
@endphp

<div class="w-full"
     style="column-width: {{ (int) $cardWidth }}px; column-gap: 4px;">
    @foreach ($this->items as $model)
        @php
            $item = $this->dataFromModel($model);
            $imageUrl = $imageField?->resolveValue($item);
            $cardUrl = $this->cardUrl($item, $model);
        @endphp

        @if ($definition?->view)
            @include($definition->view, [
                'definition' => $definition,
                'record' => $item,
                'item' => $item,
                'model' => $model,
                'titleField' => $titleField,
                'bodyFields' => $definition->bodyFields ?? [],
                'imageField' => $imageField,
                'cards' => $this->cards,
            ])
        @else
            <div wire:key="masonry-{{ get_class($model) }}-{{ $item->id }}"
                 wire:ignore.self
                 data-masonry-item
                 class="group break-inside-avoid mb-1 overflow-hidden">
                @if ($imageUrl)
                    @if ($cardUrl)
                        <a href="{{ $cardUrl }}" class="block cursor-pointer" wire:navigate>
                            <img src="{{ $imageUrl }}" alt="" class="w-full h-auto block transition-transform duration-300 ease-out group-hover:scale-105" loading="eager" decoding="async" />
                        </a>
                    @else
                        <img src="{{ $imageUrl }}" alt="" class="w-full h-auto block transition-transform duration-300 ease-out group-hover:scale-105" loading="eager" decoding="async" />
                    @endif
                @else
                    @if ($cardUrl)
                        <a href="{{ $cardUrl }}" class="w-full aspect-square bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 dark:text-zinc-600 cursor-pointer" wire:navigate>
                            <flux:icon.photo class="size-10 transition-transform duration-300 ease-out group-hover:scale-110" />
                        </a>
                    @else
                        <div class="w-full aspect-square bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                            <flux:icon.photo class="size-10 transition-transform duration-300 ease-out group-hover:scale-110" />
                        </div>
                    @endif
                @endif

                @if ($overlayViewOverride && $cardUrl)
                    <a href="{{ $cardUrl }}" class="block cursor-pointer" wire:navigate>
                        @include($overlayViewOverride, [
                            'definition' => $definition,
                            'record' => $item,
                            'item' => $item,
                            'model' => $model,
                            'titleField' => $titleField,
                            'bodyFields' => $definition->bodyFields ?? [],
                        ])
                    </a>
                @elseif ($overlayViewOverride)
                    @include($overlayViewOverride, [
                            'definition' => $definition,
                            'record' => $item,
                            'item' => $item,
                            'model' => $model,
                            'titleField' => $titleField,
                            'bodyFields' => $definition->bodyFields ?? [],
                        ])
                @elseif ($cardUrl)
                    <a href="{{ $cardUrl }}" class="absolute bottom-0 inset-x-0 px-3 py-2 bg-gradient-to-t from-black/70 via-black/40 to-transparent cursor-pointer" wire:navigate>
                        @if ($titleField)
                            <div class="text-white text-sm font-medium drop-shadow-sm truncate">
                                @include('cms::partials.card-field', ['field' => $titleField, 'record' => $item])
                            </div>
                        @endif
                        @foreach ($overlayFields as $field)
                            <div class="text-white/80 text-xs drop-shadow-sm truncate">
                                @include('cms::partials.card-field', ['field' => $field, 'record' => $item])
                            </div>
                        @endforeach
                    </a>
                @else
                    <div class="absolute bottom-0 inset-x-0 px-3 py-2 bg-gradient-to-t from-black/70 via-black/40 to-transparent pointer-events-none">
                        @if ($titleField)
                            <div class="text-white text-sm font-medium drop-shadow-sm truncate">
                                @include('cms::partials.card-field', ['field' => $titleField, 'record' => $item])
                            </div>
                        @endif
                        @foreach ($overlayFields as $field)
                            <div class="text-white/80 text-xs drop-shadow-sm truncate">
                                @include('cms::partials.card-field', ['field' => $field, 'record' => $item])
                            </div>
                        @endforeach
                    </div>
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
                                        @if ($menuAction->isFormModal())
                                            <flux:menu.item :icon="$menuAction->icon"
                                                wire:click="openActionModal('{{ $menuAction->name }}', {{ $item->id }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @elseif ($menuAction->isConfirm())
                                            <flux:menu.item :icon="$menuAction->icon"
                                                wire:click="confirmAction('{{ $menuAction->name }}', {{ $item->id }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @elseif ($menuAction->isAlpineEvent())
                                            <flux:menu.item :icon="$menuAction->icon"
                                                x-on:click="$dispatch('{{ $menuAction->alpineEventName }}', {{ Js::from($menuAction->getAlpineEventData($item)) }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @else
                                            <flux:menu.item :icon="$menuAction->icon"
                                                wire:click="{{ $menuAction->getHandler() }}({{ $item->id }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @endif
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
