@php
    $currentValue = data_get($this, $wireModel) ?? $field->default;
    $wireModelDirective = $field->wireModelDirective($field->disabled);
@endphp

<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($wireModelDirective === 'wire:model')
        <flux:radio.group wire:model="{{ $wireModel }}" variant="segmented" :value="$currentValue">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
            @endforeach
        </flux:radio.group>
    @elseif ($wireModelDirective === 'wire:model.live')
        <flux:radio.group wire:model.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
            @endforeach
        </flux:radio.group>
    @elseif (str_starts_with($wireModelDirective, 'wire:model.live.debounce.'))
        <flux:radio.group wire:model.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
            @endforeach
        </flux:radio.group>
    @elseif ($wireModelDirective === 'wire:model.change.live')
        <flux:radio.group wire:model.change.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
            @endforeach
        </flux:radio.group>
    @else
        <flux:radio.group wire:model.blur.live="{{ $wireModel }}" variant="segmented" :value="$currentValue">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:radio value="{{ $value }}" label="{{ $optionLabel }}" />
            @endforeach
        </flux:radio.group>
    @endif
</x-form-kit::form.field-shell>
