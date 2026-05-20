@use('Coda\FormKit\Fields\Select')
@use('Coda\FormKit\Fields\Number')
@use('Coda\FormKit\Fields\Duration')
@use('Coda\FormKit\Fields\Text')
@use('Coda\FormKit\Fields\Url')
@use('Coda\FormKit\Fields\Textarea')
@use('Coda\FormKit\Fields\Date')
@use('Coda\FormKit\Fields\RadioSegmented')
@use('Coda\FormKit\Fields\Pillbox')
@use('Coda\FormKit\Fields\Slider')
@use('Coda\FormKit\Fields\Relationship')
@use('Coda\FormKit\Fields\RelationshipSelector')
@use('Coda\FormKit\Fields\Repeater')
@use('Coda\Cms\Form\Fields\Tags')
@use('Coda\Cms\Display\DisplayField')
@use('Coda\FormKit\Fields\Tree')
@use('Coda\FormKit\Fields\Preset')
@use('Coda\FormKit\Fields\Search')
@use('Coda\FormKit\Fields\FileUpload')
@use('Coda\FormKit\Fields\SwitchField')
@use('App\Training\ExerciseGroupLabeler')

@props(['field', 'prefix' => null, 'repeaterItems' => null, 'currentIndex' => null])

@php
    $wireModel = $prefix ? "{$prefix}.{$field->name}" : $field->name;
    $resolvedSuffix = method_exists($field, 'resolveSuffix')
        ? $field->resolveSuffix($prefix ? (data_get($this, $prefix) ?? []) : [])
        : $resolvedSuffix ?? null;
    $fieldContext = $prefix ? (data_get($this, $prefix) ?? []) : ($this->data ?? []);
@endphp

<div {{ $attributes }}>
    @if ($field instanceof Select && $field->tree)
        @php
            $rawTreeValue = data_get($this, $wireModel);
            $treeValue = $field->multiple
                ? collect(is_array($rawTreeValue) ? $rawTreeValue : [])
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value) => (int) $value)
                    ->values()
                    ->all()
                : (is_numeric($rawTreeValue) ? (int) $rawTreeValue : null);
            $treeOptionsJson = json_encode($field->getRenderableTreeOptions(is_array($fieldContext) ? $fieldContext : []));
            $treeKey = md5($treeOptionsJson);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <div x-data="tree_select"
                data-field="{{ $field->name }}"
                data-options="{{ $treeOptionsJson }}"
                data-value='@json($treeValue)'
                data-placeholder="{{ $field->getPlaceholder() }}"
                data-wire-model="{{ $wireModel }}"
                data-multiple="{{ $field->multiple ? 'true' : 'false' }}"
                data-clearable="{{ $field->clearable ? 'true' : 'false' }}"
                data-searchable="{{ $field->searchable ? 'true' : 'false' }}"
                data-leaf-only="{{ $field->treeLeafOnly ? 'true' : 'false' }}"
                wire:ignore
                wire:key="tree-select-{{ $field->name }}-{{ $treeKey }}">
                <div data-tree-select-container></div>
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Select)
        @php
            $options = $field->getOptions(is_array($fieldContext) ? $fieldContext : []);
            $placeholder = $field->getPlaceholder();
            $hasPlaceholder = $placeholder !== null && $placeholder !== '';

            if ($field->unique && isset($repeaterItems) && isset($currentIndex)) {
                $selectedValues = collect($repeaterItems)
                    ->filter(fn($item, $idx) => $idx !== $currentIndex && !empty($item[$field->name]))
                    ->pluck($field->name)
                    ->map(fn($v) => (string) $v)
                    ->toArray();

                $options = collect($options)
                    ->filter(fn($label, $value) => !in_array((string) $value, $selectedValues, true))
                    ->toArray();
            }

            $selectVariant = $field->variant;
            if ($field->optionView && !$selectVariant) {
                $selectVariant = 'listbox';
            }
            if ($field->multiple && !$selectVariant) {
                $selectVariant = 'listbox';
            }
            if ($field->searchable && !$selectVariant) {
                $selectVariant = 'combobox';
            }
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <flux:select :wire:model.live="$field->live ? $wireModel : null"
                :wire:model.live.blur="!$field->live ? $wireModel : null" :placeholder="$placeholder"
                data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
                :searchable="$selectVariant !== 'combobox' ? $field->searchable : null" :clearable="$field->clearable" :size="$field->size">
                @if (!$field->multiple && !$field->clearable && $hasPlaceholder)
                    <flux:select.option value="">{{ $placeholder }}</flux:select.option>
                @endif
                @foreach ($options as $value => $optionLabel)
                    @if ($field->optionView)
                        <flux:select.option value="{{ $value }}">
                            @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                        </flux:select.option>
                    @else
                        <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Number)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($resolvedSuffix)
                <flux:input.group>
                    @if ($field->disabled)
                        <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
                    @elseif ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="number"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                            min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @endif
                    <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
                </flux:input.group>
            @else
                @if ($field->disabled)
                    <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
                @elseif ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @else
                    <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Duration)
        @php
            $siblingData = $prefix ? (data_get($this, $prefix) ?? []) : [];
            $isMasked = $field->isMasked($siblingData);
            $mask = $field->resolveMask($siblingData);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
            </div>
            <flux:input.group>
                @if ($isMasked)
                    <div x-data="masked_input" data-mask="{{ $mask }}" class="flex-1">
                        @if ($field->live)
                            <flux:input wire:model.live="{{ $wireModel }}" type="text"
                                data-field="{{ $field->name }}" placeholder="00:00" />
                        @else
                            <flux:input wire:model.live.blur="{{ $wireModel }}" type="text"
                                data-field="{{ $field->name }}" placeholder="00:00" />
                        @endif
                    </div>
                @else
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="number"
                            data-field="{{ $field->name }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}" />
                    @endif
                @endif
                <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
            </flux:input.group>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Text)
        @php
            $textInputClass = $field->uppercase ? 'uppercase' : null;
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($resolvedSuffix)
                <flux:input.group>
                    @if ($field->mask)
                        <div x-data="masked_input" data-mask="{{ $field->mask }}" class="flex-1">
                            @if ($field->live)
                                <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                                    :maxlength="$field->maxLength" :class="$textInputClass" />
                            @else
                                <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                                    :maxlength="$field->maxLength" :class="$textInputClass" />
                            @endif
                        </div>
                    @else
                        @if ($field->live)
                            <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                                data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                                :maxlength="$field->maxLength" :class="$textInputClass" />
                        @else
                            <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                                data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                                :maxlength="$field->maxLength" :class="$textInputClass" />
                        @endif
                    @endif
                    <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
                </flux:input.group>
            @elseif ($field->mask)
                <div x-data="masked_input" data-mask="{{ $field->mask }}">
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                            :maxlength="$field->maxLength" :class="$textInputClass" />
                    @else
                        <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                            :maxlength="$field->maxLength" :class="$textInputClass" />
                    @endif
                </div>
            @else
                @if ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="{{ $field->inputType }}"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}"
                        :maxlength="$field->maxLength" :class="$textInputClass" />
                @else
                    <flux:input wire:model.live.blur="{{ $wireModel }}" type="{{ $field->inputType }}" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}"
                        :maxlength="$field->maxLength" :class="$textInputClass" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Url)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
            </div>
            @if ($field->live)
                <flux:input wire:model.live="{{ $wireModel }}" type="url"
                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
            @else
                <flux:input wire:model.live.blur="{{ $wireModel }}" type="url"
                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Textarea)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
            </div>
            @if ($field->live)
                @if ($field->autosize)
                    <flux:textarea wire:model.live="{{ $wireModel }}" rows="auto"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @else
                    <flux:textarea wire:model.live="{{ $wireModel }}"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @endif
            @else
                @if ($field->autosize)
                    <flux:textarea wire:model.live.blur="{{ $wireModel }}" rows="auto"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @else
                    <flux:textarea wire:model.live.blur="{{ $wireModel }}"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Date)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:date-picker wire:model.live="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @else
                <flux:date-picker wire:model.live.blur="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof RadioSegmented)
        @php $currentValue = data_get($this, $wireModel) ?? $field->default; @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:radio.group wire:model.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @else
                <flux:radio.group wire:model.live.blur="{{ $wireModel }}" variant="segmented" :value="$currentValue">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Pillbox)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live && $field->debounce)
                <div wire:ignore>
                    <flux:pillbox wire:model.live.debounce.300ms="{{ $wireModel }}" multiple :searchable="$field->searchable"
                        :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                        @foreach ($field->getOptions() as $value => $optionLabel)
                            <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                        @endforeach
                    </flux:pillbox>
                </div>
            @elseif ($field->live)
                <flux:pillbox wire:model.live="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @elseif ($field->blur)
                <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @else
                <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->getOptions() as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Slider)
        <x-form-kit::form.slider-with-input :label="$field->getLabel()" :model="$wireModel" :min="$field->min" :max="$field->max"
            :step="$field->step" :suffix="$resolvedSuffix" :ticks="$field->ticks" :required="$field->required" />
    @elseif ($field instanceof Tree)
        @php
            $rawTreeValue = data_get($this, $wireModel);
            $treeValue = is_numeric($rawTreeValue) ? (int) $rawTreeValue : null;
            $treeOptionsJson = json_encode($field->getRenderableTreeOptions(is_array($fieldContext) ? $fieldContext : []));
            $treeKey = md5($treeOptionsJson);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <div x-data="tree_select"
                data-field="{{ $field->name }}"
                data-options="{{ $treeOptionsJson }}"
                data-value='@json($treeValue)'
                data-placeholder="{{ $field->getPlaceholder() }}"
                data-wire-model="{{ $wireModel }}"
                data-multiple="false"
                data-clearable="true"
                data-searchable="true"
                data-leaf-only="false"
                wire:ignore
                wire:key="tree-{{ $field->name }}-{{ $treeKey }}">
                <div data-tree-select-container></div>
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif (class_exists(Tags::class) && $field instanceof Tags)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple searchable
                placeholder="{{ $field->getPlaceholder() }}" data-field="{{ $field->name }}">
                @foreach ($field->getOptions() as $value => $optionLabel)
                    <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                @endforeach
                @if ($field->creatable)
                    <flux:pillbox.option.create
                        x-on:click="$wire.createTag('{{ $field->name }}', $el.closest('[data-flux-options]').querySelector('[data-flux-pillbox-input]').value)"
                        min-length="2"
                    >
                        Create new
                    </flux:pillbox.option.create>
                @endif
            </flux:pillbox>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof RelationshipSelector)
        @include('components.training.relationship-selector-field', [
            'field' => $field,
            'wireModel' => $wireModel,
            'items' => data_get($this, $wireModel, []),
        ])
    @elseif ($field instanceof Relationship)
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                        @if ($field->helpText)
                            <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                        @endif
                    </div>
                    <flux:button type="button" size="sm" variant="ghost"
                        wire:click="addRelationshipItem('{{ $field->name }}')" icon="plus">
                        Add
                    </flux:button>
                </div>

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
                                            placeholder="{{ $field->getPlaceholder() }}" size="sm"
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
                                        </flux:select>
                                    @else
                                        <flux:select
                                            wire:key="{{ $field->name }}-select-{{ $item['_key'] ?? $index }}"
                                            wire:model.live="{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}"
                                            placeholder="{{ $field->getPlaceholder() }}" size="sm"
                                            variant="listbox" searchable clearable
                                            data-field="{{ $field->name }}" data-index="{{ $index }}">
                                            @foreach ($filteredOptions as $value => $optionLabel)
                                                <flux:select.option value="{{ $value }}">{{ $optionLabel }}
                                                </flux:select.option>
                                            @endforeach
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
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Repeater)
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                        @if ($field->helpText)
                            <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                        @endif
                    </div>
                    <flux:button type="button" size="sm" variant="ghost"
                        wire:click="addRepeaterItem('{{ $field->name }}')" icon="plus">
                        Add
                    </flux:button>
                </div>

                @php
                    $items = data_get($this, $wireModel, []);
                @endphp

                @if (is_array($items) && count($items) > 0)
                    <div class="space-y-3">
                        @foreach ($items as $index => $item)
                            <div class="flex items-start gap-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg"
                                wire:key="{{ $field->name }}-{{ $index }}">
                                <div class="flex-1 space-y-3">
                                    @foreach ($field->schema as $childField)
                                        <x-form-kit::form.field :field="$childField" :prefix="$wireModel . '.' . $index" :repeater-items="$items"
                                            :current-index="$index" />
                                    @endforeach
                                </div>
                                <flux:button type="button" size="xs" variant="ghost" icon="trash"
                                    wire:click="removeRepeaterItem('{{ $field->name }}', {{ $index }})" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500">No items added yet.</p>
                @endif
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Preset)
        @php
            $isMapped = $field->isMapped();
            $currentValue = $isMapped ? null : data_get($this, $wireModel);
            $anyPresetActive = $isMapped
                ? collect($field->presets)->contains(fn($p) => collect($p['values'])->every(fn($v, $k) => data_get($this, "{$prefix}.{$k}") === $v))
                : collect($field->presets)->contains(fn($p) => $p['value'] === $currentValue);
        @endphp
        <div x-data="{ showOther: {{ !$anyPresetActive && $field->hasOther() ? 'true' : 'false' }} }">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($field->presets as $preset)
                    @if ($isMapped)
                        @php
                            $setCommands = collect($preset['values'])
                                ->map(fn($val, $key) => "\$wire.set('{$prefix}.{$key}', '{$val}')")
                                ->implode('; ');
                            $isActive = collect($preset['values'])
                                ->every(fn($val, $key) => data_get($this, "{$prefix}.{$key}") === $val);
                        @endphp
                        @if ($isActive)
                            <span class="inline-flex rounded-lg" x-bind:class="!showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                                <flux:button
                                    x-on:click="{{ $setCommands }}; showOther = false"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ $preset['label'] }}
                                </flux:button>
                            </span>
                        @else
                            <flux:button
                                x-on:click="{{ $setCommands }}; showOther = false"
                                variant="ghost"
                                size="sm"
                            >
                                {{ $preset['label'] }}
                            </flux:button>
                        @endif
                    @else
                        @if ($preset['value'] === $currentValue)
                            <span class="inline-flex rounded-lg" x-bind:class="!showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                                <flux:button
                                    wire:click="$set('{{ $wireModel }}', '{{ $preset['value'] }}')"
                                    x-on:click="showOther = false"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ $preset['label'] }}
                                </flux:button>
                            </span>
                        @else
                            <flux:button
                                wire:click="$set('{{ $wireModel }}', '{{ $preset['value'] }}')"
                                x-on:click="showOther = false"
                                variant="ghost"
                                size="sm"
                            >
                                {{ $preset['label'] }}
                            </flux:button>
                        @endif
                    @endif
                @endforeach
                @if ($field->hasOther())
                    <span class="inline-flex rounded-lg" x-bind:class="showOther && 'bg-zinc-200 dark:bg-zinc-700'">
                        <flux:button
                            x-on:click="showOther = !showOther"
                            variant="ghost"
                            size="sm"
                        >
                            Other
                        </flux:button>
                    </span>
                @endif
            </div>
            @if ($field->otherField)
                <div x-show="showOther" x-cloak class="mt-3">
                    <x-form-kit::form.field :field="$field->otherField" :prefix="$prefix" />
                </div>
            @elseif (!empty($field->otherFields))
                <div x-show="showOther" x-cloak class="mt-3 flex items-end gap-3">
                    @foreach ($field->otherFields as $otherField)
                        <x-form-kit::form.field :field="$otherField" :prefix="$prefix" class="flex-1" />
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($field instanceof Search)
        <flux:input wire:model.live.debounce.300ms="{{ $wireModel }}" placeholder="{{ $field->getPlaceholder() }}" size="{{ $field->size }}" clearable>
            <x-slot:icon>
                <flux:icon.magnifying-glass variant="micro" />
            </x-slot:icon>
        </flux:input>
    @elseif ($field instanceof FileUpload)
        @php
            $mediaModel = "mediaUploads.{$field->name}";
            $existingItems = $this->existingMedia[$field->name] ?? [];
            $newUploads = $this->mediaUploads[$field->name] ?? ($field->multiple ? [] : null);
            $visibleExistingItems = $field->multiple || ! $newUploads ? $existingItems : [];
            $hasSingleUpload = ! $field->multiple && (filled($newUploads) || count($visibleExistingItems) > 0);
            $hasEditors = ! empty($field->editors);
            $previewMaxWidthClasses = $field->previewMaxWidth;
            $previewAspectRatioClass = blank($field->previewAspectRatio)
                ? null
                : (str_starts_with($field->previewAspectRatio, 'aspect-')
                    ? $field->previewAspectRatio
                    : "aspect-[{$field->previewAspectRatio}]");
            $resolvePreviewObjectPosition = static function (?array $focusPoint, $useFocusPoint): string {
                if (! $useFocusPoint) {
                    return '50% 50%';
                }

                $x = is_numeric($focusPoint['x'] ?? null) ? (float) $focusPoint['x'] : 0.5;
                $y = is_numeric($focusPoint['y'] ?? null) ? (float) $focusPoint['y'] : 0.5;

                $x = max(0, min(1, $x));
                $y = max(0, min(1, $y));

                return ($x * 100).'% '.($y * 100).'%';
            };
            $formatFileSize = static function ($size): ?string {
                if ($size === null || $size === '') {
                    return null;
                }

                if (! is_numeric($size)) {
                    return (string) $size;
                }

                $size = (int) $size;

                if ($size < 1024) {
                    return round($size).' B';
                }

                if ($size < 1024 * 1024) {
                    return round($size / 1024).' KB';
                }

                if ($size < 1024 * 1024 * 1024) {
                    return round($size / 1024 / 1024).' MB';
                }

                return round($size / 1024 / 1024 / 1024).' GB';
            };
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label :badge="$field->required ? 'Required' : null">{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->multiple)
                <flux:file-upload wire:model="{{ $mediaModel }}" multiple>
                    <flux:file-upload.dropzone
                        heading="{{ $field->dropzoneHeading }}"
                        text="{{ $field->dropzoneText }}"
                        inline
                    />
                </flux:file-upload>
            @else
                @unless ($hasSingleUpload)
                    <flux:file-upload wire:model="{{ $mediaModel }}">
                        <flux:file-upload.dropzone
                            heading="{{ $field->dropzoneHeading }}"
                            text="{{ $field->dropzoneText }}"
                            inline
                        />
                    </flux:file-upload>
                @endunless
            @endif

            <div class="mt-3 flex flex-col gap-2" x-data="{ previewUrl: null }">
                @foreach ($visibleExistingItems as $media)
                    @php
                        $focusPoint = data_get($media, 'focusPoint');
                        $existingPreviewObjectPosition = $resolvePreviewObjectPosition($focusPoint, $field->previewUsesFocusPoint);
                    @endphp
                    <div class="relative w-fit max-w-full" wire:key="existing-{{ $field->name }}-{{ $media['id'] }}">
                        <div class="block cursor-pointer {{ $previewMaxWidthClasses }}" @click="previewUrl = '{{ $media['url'] }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                            @if ($previewAspectRatioClass)
                                <div class="relative overflow-hidden rounded-t-lg {{ $previewAspectRatioClass }}">
                                    <img src="{{ $media['url'] }}" alt="{{ $media['name'] }}" class="h-full w-full {{ $field->previewCropsImage ? 'object-cover' : 'object-contain' }}" style="object-position: {{ $existingPreviewObjectPosition }};" />
                                    <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                        @if ($hasEditors)
                                            <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openExistingMediaEditor('{{ $field->name }}', {{ $media['id'] }})" />
                                        @endif
                                        <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeExistingMedia('{{ $field->name }}', {{ $media['id'] }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    </div>
                                </div>
                            @else
                                <div class="relative">
                                    <img src="{{ $media['url'] }}" alt="{{ $media['name'] }}" class="h-auto w-full rounded-t-lg object-contain" />
                                    @if (is_array($focusPoint))
                                        <div
                                            class="pointer-events-none absolute z-[11] h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow"
                                            style="left: {{ ($focusPoint['x'] ?? 0.5) * 100 }}%; top: {{ ($focusPoint['y'] ?? 0.5) * 100 }}%;"
                                        ></div>
                                    @endif
                                    <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                        @if ($hasEditors)
                                            <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openExistingMediaEditor('{{ $field->name }}', {{ $media['id'] }})" />
                                        @endif
                                        <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeExistingMedia('{{ $field->name }}', {{ $media['id'] }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col gap-1 rounded-b-lg bg-zinc-800/5 px-3 py-2 dark:bg-white/10">
                            <div class="text-sm font-medium text-zinc-700 dark:text-white/80 break-all">{{ $media['name'] }}</div>
                            @if ($formatFileSize($media['size'] ?? null))
                                <div class="text-xs text-zinc-500 dark:text-white/60">{{ $formatFileSize($media['size']) }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if (is_array($newUploads))
                    @foreach ($newUploads as $index => $upload)
                        @php
                            $uploadExists = $upload->exists();
                            $uploadName = $upload->getClientOriginalName();
                            $uploadSize = $uploadExists ? $upload->getSize() : null;
                            $uploadImage = ($uploadExists && $upload->isPreviewable()) ? $upload->temporaryUrl() : null;
                            $formattedUploadSize = $formatFileSize($uploadSize);
                            $draftKey = method_exists($this, 'mediaUploadDraftKey') ? $this->mediaUploadDraftKey($field->name, $index) : null;
                            $draftFocusPoint = $draftKey ? ($this->mediaEditorDrafts[$field->name][$draftKey]['focus'] ?? null) : null;
                            $uploadPreviewObjectPosition = $resolvePreviewObjectPosition($draftFocusPoint, $field->previewUsesFocusPoint);
                        @endphp
                        @if ($uploadImage)
                            <div class="relative w-fit max-w-full" wire:key="upload-{{ $field->name }}-{{ $index }}">
                                <div class="block cursor-pointer {{ $previewMaxWidthClasses }}" @click="previewUrl = '{{ $uploadImage }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                            @if ($previewAspectRatioClass)
                                <div class="relative overflow-hidden rounded-t-lg {{ $previewAspectRatioClass }}">
                                    <img src="{{ $uploadImage }}" alt="{{ $uploadName }}" class="h-full w-full {{ $field->previewCropsImage ? 'object-cover' : 'object-contain' }}" style="object-position: {{ $uploadPreviewObjectPosition }};" />
                                            <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                                @if ($hasEditors)
                                                    <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', {{ $index }})" />
                                                @endif
                                                <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                            </div>
                                        </div>
                                    @else
                                        <div class="relative">
                                            <img src="{{ $uploadImage }}" alt="{{ $uploadName }}" class="h-auto w-full rounded-t-lg object-contain" />
                                            @if (is_array($draftFocusPoint))
                                                <div
                                                    class="pointer-events-none absolute z-[11] h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow"
                                                    style="left: {{ ($draftFocusPoint['x'] ?? 0.5) * 100 }}%; top: {{ ($draftFocusPoint['y'] ?? 0.5) * 100 }}%;"
                                                ></div>
                                            @endif
                                            <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                                @if ($hasEditors)
                                                    <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', {{ $index }})" />
                                                @endif
                                                <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-1 rounded-b-lg bg-zinc-800/5 px-3 py-2 dark:bg-white/10">
                                    <div class="text-sm font-medium text-zinc-700 dark:text-white/80 break-all">{{ $uploadName }}</div>
                                    @if ($formattedUploadSize)
                                        <div class="text-xs text-zinc-500 dark:text-white/60">{{ $formattedUploadSize }}</div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <flux:file-item
                                wire:key="upload-{{ $field->name }}-{{ $index }}"
                                :heading="$uploadName"
                                :size="$uploadSize"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', {{ $index }})" />
                                </x-slot>
                            </flux:file-item>
                        @endif
                    @endforeach
                @elseif ($newUploads)
                    @php
                        $uploadExists = $newUploads->exists();
                        $uploadName = $newUploads->getClientOriginalName();
                        $uploadSize = $uploadExists ? $newUploads->getSize() : null;
                        $uploadImage = ($uploadExists && $newUploads->isPreviewable()) ? $newUploads->temporaryUrl() : null;
                        $formattedUploadSize = $formatFileSize($uploadSize);
                        $draftKey = method_exists($this, 'mediaUploadDraftKey') ? $this->mediaUploadDraftKey($field->name, 0) : null;
                        $draftFocusPoint = $draftKey ? ($this->mediaEditorDrafts[$field->name][$draftKey]['focus'] ?? null) : null;
                        $singleUploadPreviewObjectPosition = $resolvePreviewObjectPosition($draftFocusPoint, $field->previewUsesFocusPoint);
                    @endphp
                    @if ($uploadImage)
                        <div class="relative w-fit max-w-full" wire:key="upload-{{ $field->name }}-single">
                            <div class="block cursor-pointer {{ $previewMaxWidthClasses }}" @click="previewUrl = '{{ $uploadImage }}'; $flux.modal('image-preview-{{ $field->name }}').show()">
                                @if ($previewAspectRatioClass)
                                    <div class="relative overflow-hidden rounded-t-lg {{ $previewAspectRatioClass }}">
                                        <img src="{{ $uploadImage }}" alt="{{ $uploadName }}" class="h-full w-full {{ $field->previewCropsImage ? 'object-cover' : 'object-contain' }}" style="object-position: {{ $singleUploadPreviewObjectPosition }};" />
                                        <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                            @if ($hasEditors)
                                                <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', 0)" />
                                            @endif
                                            <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', 0)" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                        </div>
                                    </div>
                                @else
                                    <div class="relative">
                                        <img src="{{ $uploadImage }}" alt="{{ $uploadName }}" class="h-auto w-full rounded-t-lg object-contain" />
                                        @if (is_array($draftFocusPoint))
                                            <div
                                                class="pointer-events-none absolute z-[11] h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-zinc-900/55 shadow"
                                                style="left: {{ ($draftFocusPoint['x'] ?? 0.5) * 100 }}%; top: {{ ($draftFocusPoint['y'] ?? 0.5) * 100 }}%;"
                                            ></div>
                                        @endif
                                        <div class="absolute top-2 z-10 flex items-center gap-1" style="right: 0.5rem; left: auto;" @click.stop>
                                            @if ($hasEditors)
                                                <flux:button type="button" variant="ghost" size="xs" icon="pencil" wire:click="openNewMediaEditor('{{ $field->name }}', 0)" />
                                            @endif
                                            <flux:button type="button" variant="ghost" size="xs" icon="trash-2" wire:click="removeNewUpload('{{ $field->name }}', 0)" class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-col gap-1 rounded-b-lg bg-zinc-800/5 px-3 py-2 dark:bg-white/10">
                                <div class="text-sm font-medium text-zinc-700 dark:text-white/80 break-all">{{ $uploadName }}</div>
                                @if ($formattedUploadSize)
                                    <div class="text-xs text-zinc-500 dark:text-white/60">{{ $formattedUploadSize }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <flux:file-item
                            wire:key="upload-{{ $field->name }}-single"
                            :heading="$uploadName"
                            :size="$uploadSize"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove @click.stop wire:click="removeNewUpload('{{ $field->name }}', 0)" />
                            </x-slot>
                        </flux:file-item>
                    @endif
                @endif

                <flux:modal :name="'image-preview-' . $field->name" variant="bare" class="w-auto max-w-[90vw] bg-transparent! shadow-none!">
                    <div class="flex items-center justify-center" @click="$flux.modal('image-preview-{{ $field->name }}').close()">
                        <img :src="previewUrl" @click.stop class="max-h-[85vh] max-w-[85vw] rounded-lg object-contain" />
                    </div>
                </flux:modal>

                @php
                    $currentMediaEditorField = $this->activeMediaEditor['fieldName'] ?? null;
                    $currentMediaEditorView = method_exists($this, 'activeMediaEditorView')
                        ? $this->activeMediaEditorView()
                        : null;
                @endphp

                @if ($hasEditors)
                    <flux:modal
                        :name="method_exists($this, 'mediaEditorModalName') ? $this->mediaEditorModalName($field->name) : 'media-editor-' . $field->name"
                        class="max-w-4xl"
                        x-on:close="$wire.closeMediaEditor()"
                    >
                        <div class="flex max-h-[90vh] flex-col gap-4">
                            <div class="shrink-0">
                                <flux:heading size="lg">Set Crop Focus</flux:heading>
                            </div>

                            <div class="flex-1">
                                @if ($currentMediaEditorField === $field->name && $currentMediaEditorView)
                                    @include($currentMediaEditorView, [
                                        'context' => $this->activeMediaEditor,
                                            'state' => $this->mediaEditorState,
                                        'field' => $field,
                                    ])
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center justify-end gap-2">
                                <flux:button type="button" variant="ghost" wire:click="cancelMediaEditor">Cancel</flux:button>
                                <flux:button type="button" variant="primary" wire:click="saveMediaEditor">Save</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            </div>
            <flux:error name="{{ $mediaModel }}" />
        </flux:field>
    @elseif ($field instanceof SwitchField)
        <flux:field>
            <div class="flex items-center gap-1">
                <flux:switch wire:model="{{ $wireModel }}" :label="$field->getLabel()" />
                @if ($field->helpText)
                    <div class="ml-auto pl-2">
                        <x-form-kit::form.help-tooltip :content="$field->helpText" position="top" />
                    </div>
                @endif
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @endif
</div>
