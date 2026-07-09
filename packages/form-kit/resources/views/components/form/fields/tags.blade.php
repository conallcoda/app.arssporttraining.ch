<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple searchable
        placeholder="{{ $explicitPlaceholder($field) }}" data-field="{{ $field->name }}">
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
</x-form-kit::form.field-shell>
