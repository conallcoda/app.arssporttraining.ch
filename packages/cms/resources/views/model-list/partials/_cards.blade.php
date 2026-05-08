@php
    use Coda\Cms\Display\DisplayFields\Image as ImageColumn;

    $layout = $this->cardLayout;
    $cardWidth = $this->cardWidth;
    $cardViewOverride = $this->cardView;

    $titleFieldName = $this->cardTitleField;
    $titleField = $titleFieldName
        ? collect($this->cards)->first(fn ($c) => $c->field === $titleFieldName)
        : collect($this->cards)->reject(fn ($c) => $c instanceof ImageColumn)->first();
    $bodyFields = collect($this->cards)
        ->reject(fn ($c) => $c instanceof ImageColumn)
        ->reject(fn ($c) => $titleField && $c === $titleField)
        ->values();
    $imageField = collect($this->cards)->first(fn ($c) => $c instanceof ImageColumn);
@endphp

@if ($layout === 'masonry')
    @include('cms::model-list.partials._cards-masonry', [
        'titleField' => $titleField,
        'bodyFields' => $bodyFields,
        'imageField' => $imageField,
        'cardWidth' => $cardWidth,
    ])

    @include('cms::model-list.partials._lightbox', [
        'titleField' => $titleField,
        'bodyFields' => $bodyFields,
        'imageField' => $imageField,
    ])
@else
    <div class="grid gap-4"
         style="grid-template-columns: repeat(auto-fill, minmax({{ $cardWidth }}px, 1fr));">
        @foreach ($this->items as $model)
            @php $item = $this->dataFromModel($model); @endphp

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
                <flux:card wire:key="card-{{ $item->id }}-{{ $this->refreshKey }}" class="!p-0 overflow-hidden flex flex-col">
                    @if ($imageField)
                        @php
                            $imageUrl = $item->{$imageField->field} ?? null;
                            $aspectClass = match ($imageField->aspect) {
                                'video' => 'aspect-video',
                                'auto' => '',
                                default => 'aspect-square',
                            };
                            $fitClass = $imageField->fit === 'contain' ? 'object-contain' : 'object-cover';
                        @endphp
                        <div class="w-full {{ $aspectClass }} bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="" class="w-full h-full {{ $fitClass }}" loading="lazy" />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                                    <flux:icon.photo class="size-10" />
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="p-4 flex-1 flex flex-col gap-3">
                        @if ($titleField)
                            <div>
                                <flux:text size="xs" variant="subtle" class="uppercase tracking-wide">{{ $titleField->getDisplayLabel() }}</flux:text>
                                <div class="mt-0.5">
                                    @include('cms::model-list.partials._field-value', ['column' => $titleField, 'item' => $item, 'model' => $model])
                                </div>
                            </div>
                        @endif

                        @foreach ($bodyFields as $column)
                            <div>
                                <flux:text size="xs" variant="subtle" class="uppercase tracking-wide">{{ $column->getDisplayLabel() }}</flux:text>
                                <div class="mt-0.5">
                                    @include('cms::model-list.partials._field-value', ['column' => $column, 'item' => $item, 'model' => $model])
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-auto pt-2 flex gap-0.5 justify-end border-t border-zinc-200 dark:border-zinc-700 -mx-4 -mb-4 px-4 py-2">
                            @foreach ($this->rowActions as $action)
                                @if (! $action->isVisible($item))
                                    @continue
                                @endif

                                @if ($action->isFormModal() && $action->name === 'edit')
                                    <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                        wire:click="startEdit({{ $item->id }})" />
                                @elseif ($action->isFormModal())
                                    <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                        x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                                            data: {{ Js::from($item->toArray()) }},
                                            title: '{{ $action->modalTitle }}'
                                        })" />
                                @elseif ($action->isConfirm())
                                    <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                        wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                                        class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                @elseif ($action->isAlpineEvent())
                                    <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                        x-on:click="$dispatch('{{ $action->alpineEventName }}', {{ Js::from($action->getAlpineEventData($item)) }})" />
                                @elseif ($action->isDirect())
                                    <flux:button type="button" size="xs" variant="ghost"
                                        icon="{{ $action->icon }}"
                                        wire:click="{{ $action->getHandler() }}({{ $item->id }})" />
                                @endif
                            @endforeach

                            @if (count($this->rowMenuActions) > 0)
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="xs" icon="ellipsis" />
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
                    </div>
                </flux:card>
            @endif
        @endforeach
    </div>
@endif

<div class="[&_[data-flux-pagination]]:!border-t-0">
    @include('cms::model-list.partials._pagination')
</div>
