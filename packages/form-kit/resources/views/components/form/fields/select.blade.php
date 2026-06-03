@php
    $options = $field->getOptions(is_array($fieldContext) ? $fieldContext : []);
    $placeholder = $explicitPlaceholder($field);
    $wireModelDirective = $field->wireModelDirective($field->disabled);

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

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($wireModelDirective === 'wire:model')
        <flux:select wire:model="{{ $wireModel }}" :placeholder="$placeholder"
            data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
            :searchable="$selectVariant !== 'combobox' ? $field->searchable : null" :clearable="$field->clearable" :size="$field->size">
            @foreach ($options as $value => $optionLabel)
                @if ($field->optionView)
                    <flux:select.option value="{{ $value }}">
                        @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                    </flux:select.option>
                @else
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endif
            @endforeach

            @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                <flux:select.option.create
                    x-data="{}"
                    x-on:click="$wire.openSelectCreateModal('{{ $field->name }}', '{{ $wireModel }}', $el.closest('[data-flux-options]')?.querySelector('[data-flux-select-search] input')?.value ?? '')"
                >
                    {{ $field->getCreateOptionLabel() }}
                </flux:select.option.create>
            @endif
        </flux:select>
    @elseif ($wireModelDirective === 'wire:model.live')
        <flux:select wire:model.live="{{ $wireModel }}" :placeholder="$placeholder"
            data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
            :searchable="$selectVariant !== 'combobox' ? $field->searchable : null" :clearable="$field->clearable" :size="$field->size">
            @foreach ($options as $value => $optionLabel)
                @if ($field->optionView)
                    <flux:select.option value="{{ $value }}">
                        @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                    </flux:select.option>
                @else
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endif
            @endforeach

            @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                <flux:select.option.create
                    x-data="{}"
                    x-on:click="$wire.openSelectCreateModal('{{ $field->name }}', '{{ $wireModel }}', $el.closest('[data-flux-options]')?.querySelector('[data-flux-select-search] input')?.value ?? '')"
                >
                    {{ $field->getCreateOptionLabel() }}
                </flux:select.option.create>
            @endif
        </flux:select>
    @elseif (str_starts_with($wireModelDirective, 'wire:model.live.debounce.'))
        <flux:select wire:model.live="{{ $wireModel }}" :placeholder="$placeholder"
            data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
            :searchable="$selectVariant !== 'combobox' ? $field->searchable : null" :clearable="$field->clearable" :size="$field->size">
            @foreach ($options as $value => $optionLabel)
                @if ($field->optionView)
                    <flux:select.option value="{{ $value }}">
                        @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                    </flux:select.option>
                @else
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endif
            @endforeach

            @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                <flux:select.option.create
                    x-data="{}"
                    x-on:click="$wire.openSelectCreateModal('{{ $field->name }}', '{{ $wireModel }}', $el.closest('[data-flux-options]')?.querySelector('[data-flux-select-search] input')?.value ?? '')"
                >
                    {{ $field->getCreateOptionLabel() }}
                </flux:select.option.create>
            @endif
        </flux:select>
    @elseif ($wireModelDirective === 'wire:model.change.live')
        <flux:select wire:model.change.live="{{ $wireModel }}" :placeholder="$placeholder"
            data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
            :searchable="$selectVariant !== 'combobox' ? $field->searchable : null" :clearable="$field->clearable" :size="$field->size">
            @foreach ($options as $value => $optionLabel)
                @if ($field->optionView)
                    <flux:select.option value="{{ $value }}">
                        @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                    </flux:select.option>
                @else
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endif
            @endforeach

            @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                <flux:select.option.create
                    x-data="{}"
                    x-on:click="$wire.openSelectCreateModal('{{ $field->name }}', '{{ $wireModel }}', $el.closest('[data-flux-options]')?.querySelector('[data-flux-select-search] input')?.value ?? '')"
                >
                    {{ $field->getCreateOptionLabel() }}
                </flux:select.option.create>
            @endif
        </flux:select>
    @else
        <flux:select wire:model.blur.live="{{ $wireModel }}" :placeholder="$placeholder"
            data-field="{{ $field->name }}" :variant="$selectVariant" :multiple="$field->multiple"
            :searchable="$selectVariant !== 'combobox' ? $field->searchable : null" :clearable="$field->clearable" :size="$field->size">
            @foreach ($options as $value => $optionLabel)
                @if ($field->optionView)
                    <flux:select.option value="{{ $value }}">
                        @include($field->optionView, ['value' => $value, 'label' => $optionLabel])
                    </flux:select.option>
                @else
                    <flux:select.option value="{{ $value }}">{{ $optionLabel }}</flux:select.option>
                @endif
            @endforeach

            @if (method_exists($field, 'hasCreateOption') && $field->hasCreateOption())
                <flux:select.option.create
                    x-data="{}"
                    x-on:click="$wire.openSelectCreateModal('{{ $field->name }}', '{{ $wireModel }}', $el.closest('[data-flux-options]')?.querySelector('[data-flux-select-search] input')?.value ?? '')"
                >
                    {{ $field->getCreateOptionLabel() }}
                </flux:select.option.create>
            @endif
        </flux:select>
    @endif
</x-form-kit::form.field-shell>
