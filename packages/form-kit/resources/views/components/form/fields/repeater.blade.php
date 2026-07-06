<x-form-kit::form.field-shell :field="$field" :error-name="$wireModel" {{ $attributes }}>
    <x-slot:actions>
        <flux:button type="button" size="sm" variant="ghost"
            wire:click="addRepeaterItem('{{ $field->name }}')" icon="plus">
            Add
        </flux:button>
    </x-slot:actions>
    <div class="space-y-3">
        @php
            $items = data_get($this, $wireModel, []);
        @endphp

        @if (is_array($items) && count($items) > 0)
            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="flex items-start gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                        wire:key="{{ $field->name }}-{{ $index }}">
                        <div class="flex-1 space-y-3">
                            @foreach ($field->schema as $childField)
                                <x-form-kit::form.field :field="$childField" :prefix="$wireModel . '.' . $index" :repeater-items="$items"
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
</x-form-kit::form.field-shell>
