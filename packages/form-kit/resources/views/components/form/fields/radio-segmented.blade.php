@php
    $currentValue = data_get($this, $wireModel) ?? $field->default;
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
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
</x-form-kit::form.field-shell>
