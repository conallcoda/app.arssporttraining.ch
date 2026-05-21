<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    @if ($field->live && $field->debounce)
        <div wire:ignore>
            <flux:pillbox wire:model.live.debounce.300ms="{{ $wireModel }}" multiple :searchable="$field->searchable"
                :placeholder="$explicitPlaceholder($field)" data-field="{{ $field->name }}">
                @foreach ($field->getOptions() as $value => $optionLabel)
                    <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </div>
    @elseif ($field->live)
        <flux:pillbox wire:model.live="{{ $wireModel }}" multiple :searchable="$field->searchable"
            :placeholder="$explicitPlaceholder($field)" data-field="{{ $field->name }}">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
            @endforeach
        </flux:pillbox>
    @elseif ($field->blur)
        <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple :searchable="$field->searchable"
            :placeholder="$explicitPlaceholder($field)" data-field="{{ $field->name }}">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
            @endforeach
        </flux:pillbox>
    @else
        <flux:pillbox wire:model.live.blur="{{ $wireModel }}" multiple :searchable="$field->searchable"
            :placeholder="$explicitPlaceholder($field)" data-field="{{ $field->name }}">
            @foreach ($field->getOptions() as $value => $optionLabel)
                <flux:pillbox.option value="{{ $value }}">{{ $optionLabel }}</flux:pillbox.option>
            @endforeach
        </flux:pillbox>
    @endif
</x-form-kit::form.field-shell>
