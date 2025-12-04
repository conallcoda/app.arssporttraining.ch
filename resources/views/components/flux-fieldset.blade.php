@props(['fieldset', 'prefix' => null, 'showLegend' => true])

@if ($showLegend)
    <div wire:key="fieldset-{{ $prefix }}" x-data="{ expanded: true }" class="border border-zinc-200 dark:border-zinc-700 rounded-lg">
        <button
            type="button"
            x-on:click="expanded = !expanded"
            class="flex items-center justify-between w-full px-4 py-3 text-left"
        >
            <flux:heading size="sm">{{ $fieldset->label }}</flux:heading>
            <flux:icon
                name="chevron-down"
                class="size-4 text-zinc-500 transition-transform duration-200"
                x-bind:class="{ 'rotate-180': !expanded }"
            />
        </button>
        <div x-show="expanded" x-collapse>
            <div class="px-4 pb-4 space-y-3">
                @foreach ($fieldset->fields as $field)
                    <x-flux-field wire:key="field-{{ $prefix }}-{{ $field->name }}" :field="$field" :prefix="$prefix" />
                @endforeach
            </div>
        </div>
    </div>
@else
    <div wire:key="fieldset-{{ $prefix }}" class="space-y-3">
        @foreach ($fieldset->fields as $field)
            <x-flux-field wire:key="field-{{ $prefix }}-{{ $field->name }}" :field="$field" :prefix="$prefix" />
        @endforeach
    </div>
@endif
