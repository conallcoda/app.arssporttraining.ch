@use('App\Cms\Form\Fields\Select')
@use('App\Cms\Form\Fields\Number')
@use('App\Cms\Form\Fields\Duration')
@use('App\Cms\Form\Fields\Text')
@use('App\Cms\Form\Fields\Date')
@use('App\Cms\Form\Fields\RadioSegmented')
@use('App\Cms\Form\Fields\Pillbox')
@use('App\Cms\Form\Fields\Slider')
@use('App\Cms\Form\Fields\Relationship')
@use('App\Cms\Form\Fields\Repeater')
@use('App\Cms\Form\Fields\Tags')
@use('App\Cms\Form\Fields\Tree')

@props(['field', 'prefix' => null, 'repeaterItems' => null, 'currentIndex' => null])

@php
    $wireModel = $prefix ? "{$prefix}.{$field->name}" : $field->name;
    $resolvedSuffix = method_exists($field, 'resolveSuffix')
        ? $field->resolveSuffix($prefix ? data_get($this, $prefix, []) : [])
        : $resolvedSuffix ?? null;
@endphp

<div {{ $attributes }}>
    @if ($field instanceof Select)
        @php
            $options = $field->options;

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
            if ($field->multiple && !$selectVariant) {
                $selectVariant = 'listbox';
            }
            if ($field->searchable && !$selectVariant) {
                $selectVariant = 'combobox';
            }
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <flux:select :wire:model.live="$field->live ? $wireModel : null"
                :wire:model="!$field->live ? $wireModel : null" placeholder="{{ $field->getPlaceholder() }}"
                data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
                :searchable="$field->searchable" :clearable="$field->clearable" :size="$field->size">
                @if (!$field->multiple)
                    <flux:select.option value="">{{ $field->getPlaceholder() }}</flux:select.option>
                @endif
                @foreach ($options as $value => $optionLabel)
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Number)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
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
                        <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
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
                    <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Duration)
        @php
            $siblingData = $prefix ? data_get($this, $prefix, []) : [];
            $isMasked = $field->isMasked($siblingData);
            $mask = $field->resolveMask($siblingData);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
            </div>
            <flux:input.group>
                @if ($isMasked)
                    <div x-data="masked_input" data-mask="{{ $mask }}" class="flex-1">
                        @if ($field->live)
                            <flux:input wire:model.live="{{ $wireModel }}" type="text"
                                data-field="{{ $field->name }}" placeholder="00:00" />
                        @else
                            <flux:input wire:model="{{ $wireModel }}" type="text"
                                data-field="{{ $field->name }}" placeholder="00:00" />
                        @endif
                    </div>
                @else
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="number"
                            data-field="{{ $field->name }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @else
                        <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            min="{{ $field->min ?? '' }}" max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}" />
                    @endif
                @endif
                <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
            </flux:input.group>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Text)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($resolvedSuffix)
                <flux:input.group>
                    @if ($field->mask)
                        <div x-data="masked_input" data-mask="{{ $field->mask }}" class="flex-1">
                            @if ($field->live)
                                <flux:input wire:model.live="{{ $wireModel }}" type="text"
                                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                            @else
                                <flux:input wire:model="{{ $wireModel }}" type="text"
                                    data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                            @endif
                        </div>
                    @else
                        @if ($field->live)
                            <flux:input wire:model.live="{{ $wireModel }}" type="text"
                                data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                        @else
                            <flux:input wire:model="{{ $wireModel }}" type="text"
                                data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                        @endif
                    @endif
                    <flux:input.group.suffix>{{ $resolvedSuffix }}</flux:input.group.suffix>
                </flux:input.group>
            @elseif ($field->mask)
                <div x-data="masked_input" data-mask="{{ $field->mask }}">
                    @if ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="text"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                    @else
                        <flux:input wire:model="{{ $wireModel }}" type="text"
                            data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                    @endif
                </div>
            @else
                @if ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="text"
                        data-field="{{ $field->name }}" placeholder="{{ $field->getPlaceholder() }}" />
                @else
                    <flux:input wire:model="{{ $wireModel }}" type="text" data-field="{{ $field->name }}"
                        placeholder="{{ $field->getPlaceholder() }}" />
                @endif
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Date)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:date-picker wire:model.live="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @else
                <flux:date-picker wire:model="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof RadioSegmented)
        @php $currentValue = data_get($this, $wireModel) ?? $field->default; @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:radio.group wire:model.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
                    @foreach ($field->options as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @else
                <flux:radio.group wire:model="{{ $wireModel }}" variant="segmented" :value="$currentValue">
                    @foreach ($field->options as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Pillbox)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            @if ($field->live)
                <flux:pillbox wire:model.live="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->options as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @else
                <flux:pillbox wire:model="{{ $wireModel }}" multiple :searchable="$field->searchable"
                    :placeholder="$field->getPlaceholder()" data-field="{{ $field->name }}">
                    @foreach ($field->options as $value => $optionLabel)
                        <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            @endif
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Slider)
        <x-cms.form.slider-with-input :label="$field->getLabel()" :model="$wireModel" :min="$field->min" :max="$field->max"
            :step="$field->step" :suffix="$resolvedSuffix" :ticks="$field->ticks" />
    @elseif ($field instanceof Tree)
        @php
            $treeValue = data_get($this, $wireModel);
            $treeValue = is_int($treeValue) ? (int) $treeValue : null;
            $treeOptionsJson = json_encode($field->treeOptions);
            $treeKey = md5($treeOptionsJson);
        @endphp
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <div x-data="tree_select" data-options="{{ $treeOptionsJson }}"
                data-value="{{ $treeValue }}" data-placeholder="{{ $field->getPlaceholder() }}"
                data-wire-model="{{ $wireModel }}" wire:ignore wire:key="tree-{{ $field->name }}-{{ $treeKey }}">
                <div data-tree-select-container></div>
            </div>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Tags)
        <flux:field>
            <div class="flex items-center gap-1 mb-2">
                <flux:label>{{ $field->getLabel() }}</flux:label>
                @if ($field->helpText)
                    <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
                @endif
            </div>
            <flux:pillbox wire:model="{{ $wireModel }}" multiple searchable
                placeholder="{{ $field->getPlaceholder() }}" data-field="{{ $field->name }}">
                @foreach ($field->options as $value => $optionLabel)
                    <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
            <flux:error name="{{ $wireModel }}" />
        </flux:field>
    @elseif ($field instanceof Relationship)
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <flux:label>{{ $field->getLabel() }}</flux:label>
                        @if ($field->helpText)
                            <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
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
                    <div class="space-y-2">
                        @foreach ($items as $index => $item)
                            @php
                                $isFirst = $index === 0;
                                $isLast = $index === count($items) - 1;
                                $currentValue = $item[$field->valueAttribute] ?? null;
                                $filteredOptions = collect($field->options)
                                    ->filter(
                                        fn($label, $value) => $value == $currentValue ||
                                            !in_array((int) $value, $selectedIds, true),
                                    )
                                    ->toArray();
                            @endphp
                            <div class="flex items-center gap-2"
                                wire:key="{{ $field->name }}-{{ $index }}">
                                <div class="flex-1">
                                    <flux:select
                                        wire:model="{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}"
                                        placeholder="{{ $field->getPlaceholder() }}" size="sm"
                                        data-field="{{ $field->name }}" data-index="{{ $index }}">
                                        <flux:select.option value="">{{ $field->getPlaceholder() }}
                                        </flux:select.option>
                                        @foreach ($filteredOptions as $value => $optionLabel)
                                            <flux:select.option value="{{ $value }}">{{ $optionLabel }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <div class="flex gap-0.5">
                                    @if ($field->sortable)
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="moveRelationshipItem('{{ $field->name }}', {{ $index }}, -1)"
                                            :disabled="$isFirst">
                                            <x-lucide-chevron-up class="w-4 h-4" />
                                        </flux:button>
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="moveRelationshipItem('{{ $field->name }}', {{ $index }}, 1)"
                                            :disabled="$isLast">
                                            <x-lucide-chevron-down class="w-4 h-4" />
                                        </flux:button>
                                    @endif
                                    <flux:button type="button" size="xs" variant="ghost"
                                        wire:click="removeRelationshipItem('{{ $field->name }}', {{ $index }})">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </flux:button>
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
                        <flux:label>{{ $field->getLabel() }}</flux:label>
                        @if ($field->helpText)
                            <x-cms.form.help-tooltip :content="$field->helpText" position="top" />
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
                                        <x-cms.form.field :field="$childField" :prefix="$wireModel . '.' . $index" :repeater-items="$items"
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
    @endif
</div>
