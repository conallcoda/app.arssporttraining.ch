@props(['group'])

@php
    $tabKey = implode('-', array_map(fn($fs) => $fs->name, $group->fieldsets));
@endphp

@if ($group->label)
    <fieldset class="min-w-0 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0">
        <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            {{ $group->label }}</legend>
        @foreach ($group->headerFields as $field)
            <x-cms.form.field wire:key="field-header-{{ $field->name }}" :field="$field" :prefix="$group->headerPrefix ?? 'data'" />
        @endforeach
        <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
            <flux:tabs scrollable>
                @foreach ($group->fieldsets as $fieldset)
                    <flux:tab :name="$fieldset->name">{{ $fieldset->label }}</flux:tab>
                @endforeach
            </flux:tabs>

            @foreach ($group->fieldsets as $fieldset)
                <flux:tab.panel :name="$fieldset->name">
                    <x-cms.form.fieldset :fieldset="$fieldset" :prefix="$fieldset->prefix ?? 'data'" :showLegend="false" />
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    </fieldset>
@else
    <div class="space-y-4">
        @foreach ($group->headerFields as $field)
            <x-cms.form.field wire:key="field-header-{{ $field->name }}" :field="$field" :prefix="$group->headerPrefix ?? 'data'" />
        @endforeach
        <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
            <flux:tabs scrollable>
                @foreach ($group->fieldsets as $fieldset)
                    <flux:tab :name="$fieldset->name">{{ $fieldset->label }}</flux:tab>
                @endforeach
            </flux:tabs>

            @foreach ($group->fieldsets as $fieldset)
                <flux:tab.panel :name="$fieldset->name">
                    <x-cms.form.fieldset :fieldset="$fieldset" :prefix="$fieldset->prefix ?? 'data'" :showLegend="false" />
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    </div>
@endif
