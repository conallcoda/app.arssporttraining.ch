@if ($field->type === 'select')
    <flux:field>
        @if ($field->label)
            <flux:label>{{ $field->label }}</flux:label>
        @endif
        <flux:select wire:model="{{ $wireModel }}" placeholder="{{ $field->placeholder ?? 'Select...' }}">
            <flux:select.option value="">{{ $field->placeholder ?? 'Select...' }}</flux:select.option>
            @foreach ($field->options as $value => $optionLabel)
                <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>
@elseif ($field->type === 'number')
    <flux:field>
        @if ($field->label)
            <flux:label>{{ $field->label }}</flux:label>
        @endif
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
        @else
            <flux:input
                wire:model.live="{{ $wireModel }}"
                type="number"
                placeholder="{{ $field->placeholder ?? $field->label }}"
                min="{{ $field->min ?? '' }}"
                max="{{ $field->max ?? '' }}"
                step="{{ $field->step ?? '' }}"
            />
        @endif
        @if ($field->suffix)
            <flux:description>{{ $field->suffix }}</flux:description>
        @endif
    </flux:field>
@elseif ($field->type === 'text')
    <flux:field>
        @if ($field->label)
            <flux:label>{{ $field->label }}</flux:label>
        @endif
        <flux:input
            wire:model="{{ $wireModel }}"
            type="text"
            @if($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
        />
    </flux:field>
@elseif ($field->type === 'repeater')
    <flux:field>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                @if ($field->label)
                    <flux:label>{{ $field->label }}</flux:label>
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
                                    <x-flux-field :field="$childField" :prefix="$wireModel . '.' . $index" />
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
