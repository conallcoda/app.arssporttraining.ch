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
        @endphp
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            @if ($field->live)
                <flux:select wire:model.live="{{ $wireModel }}" placeholder="{{ $field->placeholder ?? 'Select...' }}">
                    <flux:select.option value="">{{ $field->placeholder ?? 'Select...' }}</flux:select.option>
                    @foreach ($options as $value => $optionLabel)
                        <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="{{ $wireModel }}" placeholder="{{ $field->placeholder ?? 'Select...' }}">
                    <flux:select.option value="">{{ $field->placeholder ?? 'Select...' }}</flux:select.option>
                    @foreach ($options as $value => $optionLabel)
                        <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </flux:field>
    @elseif ($field->type === 'number')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            @if ($field->suffix)
                <flux:input.group>
                    @if ($field->disabled)
                        <flux:input
                            wire:model="{{ $wireModel }}"
                            type="number"
                            placeholder="{{ $field->placeholder ?? $field->label }}"
                            min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}"
                            disabled
                            readonly
                        />
                    @elseif ($field->live)
                        <flux:input
                            wire:model.live="{{ $wireModel }}"
                            type="number"
                            placeholder="{{ $field->placeholder ?? $field->label }}"
                            min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}"
                        />
                    @else
                        <flux:input
                            wire:model="{{ $wireModel }}"
                            type="number"
                            placeholder="{{ $field->placeholder ?? $field->label }}"
                            min="{{ $field->min ?? '' }}"
                            max="{{ $field->max ?? '' }}"
                            step="{{ $field->step ?? '' }}"
                        />
                    @endif
                    <flux:input.group.suffix>{{ $field->suffix }}</flux:input.group.suffix>
                </flux:input.group>
            @else
                @if ($field->disabled)
                    <flux:input
                        wire:model="{{ $wireModel }}"
                        type="number"
                        placeholder="{{ $field->placeholder ?? $field->label }}"
                        min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}"
                        step="{{ $field->step ?? '' }}"
                        disabled
                        readonly
                    />
                @elseif ($field->live)
                    <flux:input
                        wire:model.live="{{ $wireModel }}"
                        type="number"
                        placeholder="{{ $field->placeholder ?? $field->label }}"
                        min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}"
                        step="{{ $field->step ?? '' }}"
                    />
                @else
                    <flux:input
                        wire:model="{{ $wireModel }}"
                        type="number"
                        placeholder="{{ $field->placeholder ?? $field->label }}"
                        min="{{ $field->min ?? '' }}"
                        max="{{ $field->max ?? '' }}"
                        step="{{ $field->step ?? '' }}"
                    />
                @endif
            @endif
        </flux:field>
    @elseif ($field->type === 'text')
        <flux:field>
            @if ($field->label)
                <div class="flex items-center gap-1">
                    <flux:label>{{ $field->label }}</flux:label>
                    @if ($field->helpText)
                        <x-help-tooltip :content="$field->helpText" position="top" />
                    @endif
                </div>
            @endif
            <flux:input
                wire:model="{{ $wireModel }}"
                type="text"
                placeholder="{{ $field->placeholder ?? '' }}"
            />
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
                    <flux:button type="button" size="sm" variant="ghost" wire:click="addRepeaterItem('{{ $field->name }}')" icon="plus">
                        Add
                    </flux:button>
                </div>

                @php
                    $items = data_get($this, $wireModel, []);
                @endphp

                @if (is_array($items) && count($items) > 0)
                    <div class="space-y-3">
                        @foreach ($items as $index => $item)
                            <div class="flex items-start gap-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg" wire:key="{{ $field->name }}-{{ $index }}">
                                <div class="flex-1 space-y-3">
                                    @foreach ($field->schema as $childField)
                                        <x-flux-field
                                            :field="$childField"
                                            :prefix="$wireModel . '.' . $index"
                                            :repeater-items="$items"
                                            :current-index="$index"
                                        />
                                    @endforeach
                                </div>
                                <flux:button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="removeRepeaterItem('{{ $field->name }}', {{ $index }})"
                                />
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
