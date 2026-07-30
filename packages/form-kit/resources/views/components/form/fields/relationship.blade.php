<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <x-slot:actions>
        <flux:button type="button" size="sm" variant="ghost"
            wire:click="addRelationshipItem('{{ $field->name }}')" icon="plus">
            Add
        </flux:button>
    </x-slot:actions>
    <div class="space-y-3">
        @php
            $items = data_get($this, $wireModel, []);
            $selectedIds = collect($items)
                ->pluck($field->valueAttribute)
                ->filter()
                ->map(fn($v) => (int) $v)
                ->toArray();
        @endphp

        @if (is_array($items) && count($items) > 0)
            @php
                $itemsKey = collect($items)->pluck('_key')->filter()->implode('-') ?: count($items);
            @endphp
            @if ($field->sortable)
                <div class="space-y-2" wire:key="{{ $field->name }}-list-{{ $itemsKey }}" x-data="sortable_items('{{ $field->name }}')">
            @else
                <div class="space-y-2" wire:key="{{ $field->name }}-list-{{ $itemsKey }}">
            @endif
                @foreach ($items as $index => $item)
                    @php
                        $currentValue = $item[$field->valueAttribute] ?? null;
                        $hasSearch = $field->searchCallback !== null;
                        $searchQuery = $hasSearch
                            ? data_get($this, "relationshipSearch.{$field->name}.{$index}", '')
                            : '';
                        $excludedIds = collect($selectedIds)
                            ->reject(fn($id) => (int) $id === (int) $currentValue)
                            ->values()
                            ->all();
                        $searchResults = $hasSearch
                            ? collect($field->getSearchResults((string) $searchQuery, $currentValue, $excludedIds))
                            : collect();
                        $filteredOptions = $hasSearch
                            ? []
                            : collect($field->getOptions())
                                ->filter(
                                    fn($label, $value) => $value == $currentValue ||
                                        !in_array((int) $value, $selectedIds, true),
                                )
                                ->toArray();
                    @endphp
                    @if ($field->sortable)
                        <div class="flex items-center gap-2"
                            wire:key="{{ $field->name }}-{{ $item['_key'] ?? $index }}"
                            data-item-index="{{ $index }}"
                            @dragover="handleDragOver($event, {{ $index }})"
                            @dragleave="handleDragLeave($event)"
                            @drop="handleDrop($event, {{ $index }})">
                            <div draggable="true"
                                @dragstart="handleDragStart($event, {{ $index }})"
                                @dragend="handleDragEnd($event)"
                                class="shrink-0 cursor-grab active:cursor-grabbing text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                <flux:icon.grip class="size-4" />
                            </div>
                    @else
                        <div class="flex items-center gap-2"
                            wire:key="{{ $field->name }}-{{ $item['_key'] ?? $index }}">
                    @endif
                        @if (property_exists($field, 'groupable') && $field->groupable)
                            <div class="w-20 shrink-0">
                                <flux:select
                                    wire:key="{{ $field->name }}-group-{{ $item['_key'] ?? $index }}"
                                    wire:model.live="{{ $wireModel }}.{{ $index }}.group"
                                    placeholder="-" size="sm"
                                    variant="listbox" clearable>
                                    @foreach ($field->groupOptions as $groupValue => $groupLabel)
                                        <flux:select.option value="{{ $groupValue }}">{{ $groupLabel }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            @if ($hasSearch)
                                <flux:select
                                    wire:key="{{ $field->name }}-select-{{ $item['_key'] ?? $index }}"
                                    wire:model.live="{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}"
                                    placeholder="{{ $explicitPlaceholder($field) }}" size="sm"
                                    variant="listbox" searchable clearable :filter="false"
                                    data-field="{{ $field->name }}" data-index="{{ $index }}"
                                    data-force-bottom-options>
                                    <x-slot name="search">
                                        <flux:select.search
                                            wire:model.live.debounce.300ms="relationshipSearch.{{ $field->name }}.{{ $index }}"
                                            placeholder="Search..." />
                                    </x-slot>
                                    @foreach ($searchResults as $option)
                                        <flux:select.option value="{{ $option->getKey() }}"
                                            selected-label="{{ $option->name ?? $option->getKey() }}"
                                            wire:key="{{ $field->name }}-option-{{ $item['_key'] ?? $index }}-{{ $option->getKey() }}">
                                            @if ($field->optionView)
                                                @include($field->optionView, ['option' => $option])
                                            @else
                                                {{ $option->name ?? $option->getKey() }}
                                            @endif
                                        </flux:select.option>
                                    @endforeach
                                    <x-slot name="empty">
                                        <flux:select.option.empty when-loading="Searching...">
                                            No matches.
                                        </flux:select.option.empty>
                                    </x-slot>
                                    @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                                        <flux:select.option.create
                                            wire:click="openRelationshipCreateModal('{{ $field->name }}', '{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}', {{ $index }}, @js($searchQuery))">
                                            {{ $field->getCreateOptionLabel() }}
                                        </flux:select.option.create>
                                    @endif
                                </flux:select>
                            @else
                                <flux:select
                                    wire:key="{{ $field->name }}-select-{{ $item['_key'] ?? $index }}"
                                    wire:model.live="{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}"
                                    placeholder="{{ $explicitPlaceholder($field) }}" size="sm"
                                    variant="listbox" searchable clearable
                                    data-field="{{ $field->name }}" data-index="{{ $index }}">
                                    @foreach ($filteredOptions as $value => $optionLabel)
                                        <flux:select.option value="{{ $value }}">{{ $optionLabel }}
                                        </flux:select.option>
                                    @endforeach
                                    @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                                        <flux:select.option.create
                                            x-data="{}"
                                            x-on:click="$wire.openRelationshipCreateModal('{{ $field->name }}', '{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}', {{ $index }}, $el.closest('[data-flux-options]')?.querySelector('[data-flux-select-search] input')?.value ?? '')">
                                            {{ $field->getCreateOptionLabel() }}
                                        </flux:select.option.create>
                                    @endif
                                </flux:select>
                            @endif
                        </div>
                        <div class="flex gap-0.5">
                            <flux:button type="button" size="xs" variant="ghost" icon="trash-2"
                                wire:click="removeRelationshipItem('{{ $field->name }}', {{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-zinc-500">No items added yet.</p>
        @endif
    </div>
</x-form-kit::form.field-shell>
