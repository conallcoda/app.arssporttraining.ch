<div>
    @if ($field->type === 'select')
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
            @if ($field->label)
                <div class="flex items-center gap-1 mb-2">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            <flux:select
                :wire:model.live="$field->live ? $wireModel : null"
                :wire:model="!$field->live ? $wireModel : null"
                placeholder="{{ $field->placeholder ?? 'Select...' }}"
                data-field="{{ $field->name }}"
                :variant="$selectVariant"
                :multiple="$field->multiple"
                :searchable="$field->searchable"
                :clearable="$field->clearable"
                :size="$field->size"
            >
                @if (!$field->multiple)
                    <flux:select.option value="">{{ $field->placeholder ?? 'Select...' }}</flux:select.option>
                @endif
                @foreach ($options as $value => $optionLabel)
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    @elseif ($field->type === 'number')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1 mb-2">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            @if ($field->suffix)
                <flux:input.group>
                    @if ($field->disabled)
                        <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->placeholder ?? $field->label }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
                    @elseif ($field->live)
                        <flux:input wire:model.live="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->placeholder ?? $field->label }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @else
                        <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                            placeholder="{{ $field->placeholder ?? $field->label }}" min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                    @endif
                    <flux:input.group.suffix>{{ $field->suffix }}</flux:input.group.suffix>
                </flux:input.group>
            @else
                @if ($field->disabled)
                    <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->placeholder ?? $field->label }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" disabled readonly />
                @elseif ($field->live)
                    <flux:input wire:model.live="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->placeholder ?? $field->label }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @else
                    <flux:input wire:model="{{ $wireModel }}" type="number" data-field="{{ $field->name }}"
                        placeholder="{{ $field->placeholder ?? $field->label }}" min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}" step="{{ $field->step ?? '' }}" />
                @endif
            @endif
        </flux:field>
    @elseif ($field->type === 'text')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1 mb-2">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            @if ($field->suffix)
                <flux:input.group>
                    @if ($field->mask)
                        <div x-data="masked_input" data-mask="{{ $field->mask }}" class="flex-1">
                            <flux:input wire:model="{{ $wireModel }}" type="text" data-field="{{ $field->name }}"
                                placeholder="{{ $field->placeholder ?? '' }}" />
                        </div>
                    @else
                        <flux:input wire:model="{{ $wireModel }}" type="text" data-field="{{ $field->name }}"
                            placeholder="{{ $field->placeholder ?? '' }}" />
                    @endif
                    <flux:input.group.suffix>{{ $field->suffix }}</flux:input.group.suffix>
                </flux:input.group>
            @elseif ($field->mask)
                <div x-data="masked_input" data-mask="{{ $field->mask }}">
                    <flux:input wire:model="{{ $wireModel }}" type="text" data-field="{{ $field->name }}"
                        placeholder="{{ $field->placeholder ?? '' }}" />
                </div>
            @else
                <flux:input wire:model="{{ $wireModel }}" type="text" data-field="{{ $field->name }}"
                    placeholder="{{ $field->placeholder ?? '' }}" />
            @endif
        </flux:field>
    @elseif ($field->type === 'date')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1 mb-2">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            @if ($field->live)
                <flux:date-picker wire:model.live="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @else
                <flux:date-picker wire:model="{{ $wireModel }}" data-field="{{ $field->name }}" />
            @endif
        </flux:field>
    @elseif ($field->type === 'radioSegmented')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1 mb-2">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            @if ($field->live)
                <flux:radio.group wire:model.live="{{ $wireModel }}" variant="segmented">
                    @foreach ($field->options as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @else
                <flux:radio.group wire:model="{{ $wireModel }}" variant="segmented">
                    @foreach ($field->options as $value => $optionLabel)
                        <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
                    @endforeach
                </flux:radio.group>
            @endif
        </flux:field>
    @elseif ($field->type === 'pillbox')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1 mb-2">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            <flux:pillbox
                wire:model.live="{{ $wireModel }}"
                multiple
                :searchable="$field->searchable"
                :placeholder="$field->placeholder ?? 'Select...'"
                data-field="{{ $field->name }}"
            >
                @foreach ($field->options as $value => $optionLabel)
                    <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </flux:field>
    @elseif ($field->type === 'slider')
        <x-slider-with-input
            :label="$field->label"
            :model="$wireModel"
            :min="$field->min"
            :max="$field->max"
            :step="$field->step"
            :suffix="$field->suffix"
            :ticks="$field->ticks"
        />
    @elseif ($field->type === 'relationship')
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    @if ($field->label)
                        <div class="flex items-center gap-1">
                            <flux:label>{{ $field->label }}</flux:label>
                            @if ($field->helpText)
                                <x-help-tooltip :content="$field->helpText" position="top" />
                            @endif
                        </div>
                    @endif
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
                                wire:key="{{ $field->name }}-{{ $currentValue ?? 'new-' . $index }}">
                                <div class="flex-1">
                                    <flux:select
                                        wire:model="{{ $wireModel }}.{{ $index }}.{{ $field->valueAttribute }}"
                                        placeholder="{{ $field->placeholder ?? 'Select...' }}" size="sm"
                                        data-field="{{ $field->name }}" data-index="{{ $index }}">
                                        <flux:select.option value="">{{ $field->placeholder ?? 'Select...' }}
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
        </flux:field>
    @elseif ($field->type === 'repeater')
        <flux:field>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    @if ($field->label)
                        <div class="flex items-center gap-1">
                            <flux:label>{{ $field->label }}</flux:label>
                            @if ($field->helpText)
                                <x-help-tooltip :content="$field->helpText" position="top" />
                            @endif
                        </div>
                    @endif
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
                                        <x-flux-field :field="$childField" :prefix="$wireModel . '.' . $index" :repeater-items="$items"
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
        </flux:field>
    @endif
</div>
