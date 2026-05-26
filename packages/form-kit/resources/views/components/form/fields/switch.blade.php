<flux:field {{ $attributes }}>
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
