@use('Coda\Cms\Display\DisplayField')

@php
    $items = data_get($this, $wireModel, []);
    $selectorItems = $field->deferModalApply
        ? ($this->relationshipSelectorDraftItems[$field->name] ?? $items)
        : $items;
    $selectedIds = collect($selectorItems)
        ->pluck($field->valueAttribute)
        ->filter(fn($value) => $value !== null && $value !== '')
        ->map(fn($value) => (string) $value)
        ->values()
        ->all();
    $filters = data_get($this, "relationshipSelectorFilters.{$field->name}", []);
    $searchQuery = data_get($this, "relationshipSelectorSearch.{$field->name}", '');
    $sort = data_get($this, "relationshipSelectorSort.{$field->name}", [
        'field' => $field->defaultSortField,
        'direction' => $field->defaultSortDirection,
    ]);
    $activeSelectorTab = data_get($this, "relationshipSelectorTab.{$field->name}", 'results');
    $modalName = method_exists($this, 'relationshipSelectorModalName')
        ? $this->relationshipSelectorModalName($field->name)
        : 'relationship-selector-' . md5($field->name);
    $selectedRecords = collect($field->getSelectedRecords($selectedIds, is_array($filters) ? $filters : [], is_array($selectorItems) ? $selectorItems : []))
        ->mapWithKeys(function ($record) use ($field) {
            $key = $field->resolveRecordKey($record);

            return $key === null ? [] : [(string) $key => $record];
        });
    $displayedResults = $field->clientModal
        ? collect()
        : ($activeSelectorTab === 'results'
            ? collect($field->getSearchResults(
                $searchQuery,
                $selectedIds,
                [],
                is_array($filters) ? $filters : [],
                is_array($selectorItems) ? $selectorItems : [],
                is_array($sort) ? $sort : [],
            ))->values()
            : collect());
    $selectedOnlyResults = $field->clientModal
        ? collect()
        : ($activeSelectorTab === 'selected'
            ? collect($selectorItems)
                ->map(fn ($item) => isset($item[$field->valueAttribute]) ? $selectedRecords->get((string) $item[$field->valueAttribute]) : null)
                ->filter()
                ->values()
            : collect());
    $visibleRows = $field->clientModal
        ? collect()
        : ($activeSelectorTab === 'selected'
            ? $selectedOnlyResults
            : $displayedResults);
    $selectionView = $field->selectionView ?? $field->resultView;
    $itemsKey = collect($items)->pluck('_key')->filter()->implode('-') ?: count($items);
    $selectorItemsKey = collect($selectorItems)->pluck('_key')->filter()->implode('-') ?: count($selectorItems);
    $columns = collect($field->resultColumns)
        ->filter(fn ($column) => $column instanceof DisplayField)
        ->values();
    $selectedTabSortable = $field->sortable && $activeSelectorTab === 'selected';
    $selectorToggleAction = $field->deferModalApply ? 'toggleRelationshipSelectorDraftItem' : 'toggleRelationshipSelectorItem';
    $clientModalListsPayload = method_exists($field, 'getClientModalListPayload')
        ? $field->getClientModalListPayload()
        : [];
@endphp

<flux:field {{ $attributes }}>
    @if ($field->clientModal)
        @include('form-kit::components.form.relationship-selector-client-modal', [
            'field' => $field,
            'modalName' => $modalName,
            'wireModel' => $wireModel,
            'items' => $items,
            'selectedRecords' => $selectedRecords,
            'selectionView' => $selectionView,
            'itemsKey' => $itemsKey,
            'clientModalListsPayload' => $clientModalListsPayload,
        ])
    @else
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-1">
                    <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                    @if ($field->helpText)
                        <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
                <flux:button
                    type="button"
                    size="sm"
                    variant="ghost"
                    wire:click="openRelationshipSelector({{ \Illuminate\Support\Js::from($field->name) }})"
                    icon="plus"
                >
                    {{ $field->selectButtonLabel }}
                </flux:button>
            </div>

            @if (is_array($items) && count($items) > 0)
                @if ($field->sortable)
                    <div class="space-y-3" wire:key="{{ $field->name }}-selector-list-{{ $itemsKey }}" x-data="sortable_items('{{ $field->name }}')">
                @else
                    <div class="space-y-3" wire:key="{{ $field->name }}-selector-list-{{ $itemsKey }}">
                @endif
                    @foreach ($items as $index => $item)
                        @php
                            $selectedValue = $item[$field->valueAttribute] ?? null;
                            $selectedRecord = $selectedValue !== null
                                ? $selectedRecords->get((string) $selectedValue)
                                : null;
                            $selectedLabel = $selectedRecord
                                ? $field->resolveRecordLabel($selectedRecord)
                                : (string) ($selectedValue ?? '');
                        @endphp
                        @if ($field->sortable)
                            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                                wire:key="{{ $field->name }}-selector-item-{{ $item['_key'] ?? $index }}"
                                data-item-index="{{ $index }}"
                                @dragover="handleDragOver($event, {{ $index }})"
                                @dragleave="handleDragLeave($event)"
                                @drop="handleDrop($event, {{ $index }})">
                                <div class="flex items-start gap-3">
                                    <div draggable="true"
                                        @dragstart="handleDragStart($event, {{ $index }})"
                                        @dragend="handleDragEnd($event)"
                                        class="mt-1 shrink-0 cursor-grab active:cursor-grabbing text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                        <flux:icon.grip class="size-4" />
                                    </div>
                        @else
                            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                                wire:key="{{ $field->name }}-selector-item-{{ $item['_key'] ?? $index }}">
                                <div class="flex items-start gap-3">
                        @endif
                                    <div class="min-w-0 flex-1 space-y-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                @if ($selectionView && $selectedRecord)
                                                    @include($selectionView, ['option' => $selectedRecord, 'selectedItem' => $item, 'field' => $field])
                                                @else
                                                    <div class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $selectedLabel !== '' ? $selectedLabel : 'Selected item' }}
                                                    </div>
                                                @endif
                                            </div>
                                            <flux:button type="button" size="xs" variant="ghost" icon="trash-2"
                                                wire:click="removeRelationshipSelectorItem({{ \Illuminate\Support\Js::from($field->name) }}, {{ $index }})" />
                                        </div>

                                        @if (! empty($field->schema))
                                            <div class="space-y-3">
                                                @foreach ($field->schema as $childField)
                                                    <x-form-kit::form.field :field="$childField" :prefix="$wireModel . '.' . $index" />
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-zinc-500">{{ $field->emptySelectionText }}</p>
            @endif
        </div>
        <flux:error name="{{ $wireModel }}" />
        <flux:modal :name="$modalName" class="w-[96vw] max-w-6xl overflow-hidden">
            <div class="-mx-6 -mb-6" x-data="relationship_selector_sort_group('{{ $field->name }}')">
                <div class="px-8 pb-4 pt-8">
                    <div class="text-base font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $field->modalTitle ?? $field->getLabel() }}
                    </div>
                </div>
                <div class="border-t border-zinc-200 dark:border-zinc-700"></div>

                @if (! empty($field->buttonFilters))
                    <div class="space-y-3 px-6 pb-4">
                        @foreach ($field->buttonFilters as $filterDefinition)
                            @php
                                $filterName = $filterDefinition['name'] ?? null;
                                $filterLabel = $filterDefinition['label'] ?? null;
                                $filterOptions = $field->resolveButtonFilterOptions($filterDefinition);
                                $selectedFilterValues = collect(data_get($this, "relationshipSelectorFilters.{$field->name}.{$filterName}", []))
                                    ->filter(fn($value) => $value !== null && $value !== '')
                                    ->map(fn($value) => (string) $value)
                                    ->values()
                                    ->all();
                            @endphp
                            @if ($filterName)
                                <div class="space-y-2">
                                    @if ($filterLabel)
                                        <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $filterLabel }}</div>
                                    @endif
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($filterOptions as $option)
                                            @php
                                                $optionValue = (string) ($option['value'] ?? '');
                                                $isActive = in_array($optionValue, $selectedFilterValues, true);
                                                $optionColor = $option['color'] ?? null;
                                                $buttonClass = $optionColor
                                                    ? \Coda\Cms\Support\ColorPalette::lightBadge((string) $optionColor)
                                                    : '';
                                            @endphp
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="{{ $isActive ? 'primary' : 'ghost' }}"
                                                class="{{ $buttonClass }}"
                                                wire:click="toggleRelationshipSelectorFilterOption({{ \Illuminate\Support\Js::from($field->name) }}, {{ \Illuminate\Support\Js::from($filterName) }}, {{ \Illuminate\Support\Js::from($optionValue) }})"
                                            >
                                                {{ $option['label'] ?? $optionValue }}
                                            </flux:button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if (! empty($field->filterFields))
                    <div class="grid gap-3 px-4 pb-4 md:grid-cols-2">
                        @foreach ($field->filterFields as $filterField)
                            <x-form-kit::form.field :field="$filterField" :prefix="'relationshipSelectorFilters.' . $field->name" />
                        @endforeach
                    </div>
                @endif

                <div class="px-8 py-4">
                    <input
                        wire:model.live.debounce.300ms="relationshipSelectorSearch.{{ $field->name }}"
                        type="search"
                        placeholder="{{ $field->searchPlaceholder }}"
                        class="w-full rounded-xl border border-zinc-800/10 bg-transparent px-4 py-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0 dark:border-white/20 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                    >
                </div>

                <div class="pt-3">
                    <flux:tab.group class="w-full">
                        <flux:tabs wire:model.live="relationshipSelectorTab.{{ $field->name }}" class="w-full px-8 text-sm">
                            <flux:tab name="results" class="text-sm">Results</flux:tab>
                            <flux:tab name="selected">
                                <span class="inline-flex items-center gap-2 text-sm">
                                    <span>Selected</span>
                                    <flux:badge size="sm" color="zinc">{{ count($selectedIds) }}</flux:badge>
                                </span>
                            </flux:tab>
                        </flux:tabs>
                    </flux:tab.group>
                </div>

                @if ($selectedTabSortable)
                    <div
                        class="h-[60vh]"
                        wire:key="{{ $field->name }}-selector-selected-{{ $selectorItemsKey }}"
                    >
                @else
                    <div class="h-[60vh]">
                @endif
                    @if ($selectedTabSortable)
                        <div
                            class="h-[60vh] overflow-y-auto divide-y divide-zinc-200 transition-all duration-200 dark:divide-zinc-700"
                            x-ref="selectedList"
                            x-init="registerSelectedList()"
                            x-sort="handleSort($item, $position)"
                        >
                    @else
                        <div class="h-[60vh] overflow-y-auto divide-y divide-zinc-200 transition-all duration-200 dark:divide-zinc-700">
                    @endif
                        @forelse ($visibleRows as $result)
                            @include('form-kit::components.form.relationship-selector-result-row', [
                                'field' => $field,
                                'result' => $result,
                                'selectedIds' => $selectedIds,
                                'items' => $selectorItems,
                                'columns' => $columns,
                                'loopIndex' => $loop->index,
                                'sortableRow' => $selectedTabSortable,
                                'rowIndex' => $loop->index,
                                'toggleAction' => $selectorToggleAction,
                            ])
                        @empty
                            <div class="px-8 py-6 text-sm text-zinc-500">
                                {{ $activeSelectorTab === 'selected' ? $field->emptySelectionText : 'No matches found.' }}
                            </div>
                        @endforelse
                    </div>
                </div>

                @if ($field->deferModalApply)
                    <div class="border-t border-zinc-200 px-8 py-6 dark:border-zinc-700">
                        <div class="flex items-center justify-end gap-3">
                            <flux:button type="button" variant="ghost" wire:click="cancelRelationshipSelectorDraft({{ \Illuminate\Support\Js::from($field->name) }})">
                                Cancel
                            </flux:button>
                            <flux:button
                                type="button"
                                variant="primary"
                                x-on:click="$wire.applyRelationshipSelectorDraft({{ \Illuminate\Support\Js::from($field->name) }}, orderedKeys())"
                            >
                                Save
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endif
</flux:field>
