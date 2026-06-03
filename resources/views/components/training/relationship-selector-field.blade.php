@php
    $selectedIds = collect($items)
        ->pluck($field->valueAttribute)
        ->filter(fn($value) => $value !== null && $value !== '')
        ->map(fn($value) => (string) $value)
        ->values()
        ->all();
    $modalName = method_exists($this, 'relationshipSelectorModalName')
        ? $this->relationshipSelectorModalName($field->name)
        : 'relationship-selector-' . md5($field->name);
    $selectedRecords = collect($field->getSelectedRecords($selectedIds, [], is_array($items) ? $items : []))
        ->mapWithKeys(function ($record) use ($field) {
            $key = $field->resolveRecordKey($record);

            return $key === null ? [] : [(string) $key => $record];
        });
    $selectionView = $field->selectionView ?? $field->resultView;
    $itemsKey = collect($items)->pluck('_key')->filter()->implode('-') ?: count($items);
    $clientModalListsPayload = method_exists($field, 'getClientModalListPayload')
        ? $field->getClientModalListPayload()
        : [];
    $saveButtonList = collect($clientModalListsPayload)->first(fn (array $list): bool => (bool) data_get($list, 'saveButton.visible', false));
    $saveButtonListKey = is_array($saveButtonList) ? (string) ($saveButtonList['key'] ?? '') : '';
    $saveButtonLabel = is_array($saveButtonList) ? (string) data_get($saveButtonList, 'saveButton.label', 'Save') : 'Save';
    $saveButtonShowExpr = $saveButtonListKey !== '' ? "activeListKey === '".str_replace("'", "\\'", $saveButtonListKey)."'" : 'true';
@endphp

<flux:field>
    <div
        class="space-y-3"
        x-data="relationship_selector_modal({
            fieldName: {{ \Illuminate\Support\Js::from($field->name) }},
            modalName: {{ \Illuminate\Support\Js::from($modalName) }},
            limit: 40,
            applyAction: {{ \Illuminate\Support\Js::from($field->clientModalSaveAction ?? 'applyRelationshipSelectorClientState') }},
            valueAttribute: {{ \Illuminate\Support\Js::from($field->valueAttribute) }},
            schemaDefaults: {{ \Illuminate\Support\Js::from(\Coda\FormKit\Field::buildDefaults($field->schema)) }},
            lists: {{ \Illuminate\Support\Js::from($clientModalListsPayload) }}
        })"
        x-on:relationship-selector-open.window="if (($event.detail?.fieldName ?? '') === fieldName || ($event.detail?.modalName ?? '') === modalName) open()"
    >
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
                x-on:click="open()"
                icon="{{ $field->triggerButtonIcon ?? 'plus' }}"
            >
                {{ $field->triggerButtonLabel ?? $field->selectButtonLabel }}
            </flux:button>
        </div>

        @if (is_array($items) && count($items) > 0)
            @if (($field->inlineSelectionDisplay ?? 'default') === 'badges')
                @php
                    $inlineGroupObjects = collect($items)
                        ->map(fn (array $item): object => (object) $item)
                        ->all();
                    $inlineGroupLabels = \App\Training\ExerciseGroupLabeler::label(
                        $inlineGroupObjects,
                        fn (object $item): ?string => $item->group ?? null,
                        fn (object $item): string => (string) ($item->_key ?? ''),
                    );
                    $inlineGroupColors = \App\Training\ExerciseGroupLabeler::colors(
                        $inlineGroupObjects,
                        fn (object $item): ?string => $item->group ?? null,
                        fn (object $item): string => (string) ($item->_key ?? ''),
                    );
                @endphp
                <div class="flex flex-wrap gap-2" wire:key="{{ $field->name }}-selector-badges-{{ $itemsKey }}">
                    @foreach ($items as $index => $item)
                        @php
                            $selectedValue = $item[$field->valueAttribute] ?? null;
                            $selectedRecord = $selectedValue !== null
                                ? $selectedRecords->get((string) $selectedValue)
                                : null;
                            $selectedLabel = $selectedRecord
                                ? $field->resolveRecordLabel($selectedRecord)
                                : (string) ($selectedValue ?? '');
                            $groupLabel = $inlineGroupLabels[(string) ($item['_key'] ?? '')] ?? null;
                            $badgeColor = $inlineGroupColors[(string) ($item['_key'] ?? '')] ?? 'zinc';
                            $badgeLabel = $selectedLabel !== ''
                                ? ($groupLabel ? $groupLabel . ' - ' . $selectedLabel : $selectedLabel)
                                : 'Selected item';
                        @endphp
                        <flux:badge
                            rounded
                            color="{{ $badgeColor }}"
                            class="inline-flex max-w-full items-center gap-2 px-3 py-1.5 text-sm"
                            wire:key="{{ $field->name }}-selector-badge-{{ $item['_key'] ?? $index }}"
                        >
                            <span class="max-w-[18rem] truncate">{{ $badgeLabel }}</span>
                            <button
                                type="button"
                                class="inline-flex items-center text-zinc-400 transition-colors hover:text-zinc-100"
                                wire:click="removeRelationshipSelectorItem({{ \Illuminate\Support\Js::from($field->name) }}, {{ $index }})"
                                aria-label="Remove {{ $badgeLabel }}"
                            >
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </flux:badge>
                    @endforeach
                </div>
            @else
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

                                        @if (($field->showInlineSchema ?? true) && ! empty($field->schema))
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
            @endif
        @else
            <p class="text-sm text-zinc-500">{{ $field->emptySelectionText }}</p>
        @endif

        <flux:error name="{{ $wireModel }}" />

        <x-cms::modal :name="$modalName" max-width="h-[90vh] md:h-[70vh] w-[92vw] max-w-4xl overflow-hidden">
            <x-cms::modal.header :title="$field->modalTitle ?? $field->getLabel()" />

            <x-cms::modal.body class="flex min-h-0 flex-1 flex-col">
                <div class="flex min-h-0 flex-1 flex-col space-y-6">
                    <div x-show="currentListSearchable()">
                        <input
                            x-model="search"
                            x-on:input="queueSearch()"
                            type="search"
                            x-bind:placeholder="currentSearchPlaceholder()"
                            class="w-full rounded-xl border border-zinc-800/10 bg-transparent px-4 py-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0 dark:border-white/20 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                        >
                    </div>

                    <div>
                        <flux:tabs x-ref="tabs" x-model="activeListKey" class="w-full text-sm">
                            @foreach ($clientModalListsPayload as $clientList)
                                <flux:tab
                                    name="{{ $clientList['key'] }}"
                                    x-bind:selected="activeListKey === '{{ $clientList['key'] }}'"
                                    class="text-sm"
                                    x-on:click="setActiveList('{{ $clientList['key'] }}')"
                                    x-bind:class="{ 'opacity-100': activeListKey === '{{ $clientList['key'] }}', 'opacity-60': activeListKey !== '{{ $clientList['key'] }}' }"
                                >
                                    <span class="inline-flex items-center gap-2 text-sm">
                                        <span>{{ $clientList['label'] }}</span>
                                        @if (! empty($clientList['badge']))
                                            <flux:badge size="sm" color="zinc" x-text="listBadgeValue('{{ $clientList['key'] }}')"></flux:badge>
                                        @endif
                                    </span>
                                </flux:tab>
                            @endforeach
                        </flux:tabs>
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden">
                        @foreach ($clientModalListsPayload as $clientList)
                            <div
                                x-show="activeListKey === '{{ $clientList['key'] }}'"
                                class="h-full overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700"
                            >
                                @if (! empty($clientList['panelFields']))
                                    <div class="space-y-3 px-3 py-3">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            @foreach ($clientList['panelFields'] as $panelField)
                                                @include('form-kit::components.form.relationship-selector-client-panel-field', ['panelField' => $panelField])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <template x-if="isListLoading('{{ $clientList['key'] }}')">
                                    <div class="py-6 text-sm text-zinc-500">Loading…</div>
                                </template>

                                <template x-if="!isListLoading('{{ $clientList['key'] }}') && rowsFor('{{ $clientList['key'] }}').length === 0">
                                    <div class="py-6 text-sm text-zinc-500">{{ $clientList['emptyText'] }}</div>
                                </template>

                                <template x-for="(row, rowIndex) in rowsFor('{{ $clientList['key'] }}')" :key="rowTemplateKey('{{ $clientList['key'] }}', row)">
                                    @include('form-kit::components.form.relationship-selector-client-row', [
                                        'listExpr' => "'".$clientList['key']."'",
                                        'rowExpr' => 'rowRecord('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'keyExpr' => 'rowKey('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'selectedExpr' => 'isRowSelected('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'clickExpr' => ! empty($clientList['rowAction']['name'])
                                            ? 'handleRowClick('.\Illuminate\Support\Js::from($clientList['key']).', row)'
                                            : '',
                                        'columnsExpr' => 'rowColumns('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'buttonVisibleExpr' => 'rowButtonVisible('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'buttonLabelExpr' => 'rowButtonLabel('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'buttonIconOnlyExpr' => 'rowButtonIconOnly('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'buttonClassExpr' => 'rowButtonClasses('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'buttonActionExpr' => 'handleButtonClick('.\Illuminate\Support\Js::from($clientList['key']).', row)',
                                        'itemFieldsExpr' => 'rowItemFields('.\Illuminate\Support\Js::from($clientList['key']).')',
                                        'clickEnabled' => ! empty($clientList['rowAction']['name']),
                                        'sortableExpr' => 'isListSortable('.\Illuminate\Support\Js::from($clientList['key']).')',
                                        'moveUpVisibleExpr' => 'showRowMoveControls('.\Illuminate\Support\Js::from($clientList['key']).')',
                                        'moveDownVisibleExpr' => 'showRowMoveControls('.\Illuminate\Support\Js::from($clientList['key']).')',
                                        'moveUpDisabledExpr' => '!canMoveRow('.\Illuminate\Support\Js::from($clientList['key']).', rowIndex, -1)',
                                        'moveDownDisabledExpr' => '!canMoveRow('.\Illuminate\Support\Js::from($clientList['key']).', rowIndex, 1)',
                                        'moveUpActionExpr' => 'moveRow('.\Illuminate\Support\Js::from($clientList['key']).', rowIndex, -1)',
                                        'moveDownActionExpr' => 'moveRow('.\Illuminate\Support\Js::from($clientList['key']).', rowIndex, 1)',
                                    ])
                                </template>

                                @if (! empty($clientList['loader']))
                                    <template x-if="listHasMore('{{ $clientList['key'] }}')">
                                        <div
                                            :key="`{{ $clientList['key'] }}-sentinel-${listState('{{ $clientList['key'] }}').sentinelKey}`"
                                            x-intersect.once="loadMore('{{ $clientList['key'] }}')"
                                            class="py-4 text-sm text-zinc-500"
                                        >
                                            <span x-show="listState('{{ $clientList['key'] }}').loadingMore">Loading more…</span>
                                        </div>
                                    </template>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-cms::modal.body>

            <x-cms::modal.footer>
                <div class="flex items-center justify-end gap-2">
                    <flux:button type="button" variant="ghost" x-on:click="cancel()">
                        Cancel
                    </flux:button>
                    <template x-if="{{ $saveButtonShowExpr }}">
                        <flux:button
                            type="button"
                            variant="primary"
                            x-on:click="save()"
                            x-bind:disabled="saving"
                        >
                            {{ $saveButtonLabel }}
                        </flux:button>
                    </template>
                </div>
            </x-cms::modal.footer>
        </x-cms::modal>
    </div>
</flux:field>
