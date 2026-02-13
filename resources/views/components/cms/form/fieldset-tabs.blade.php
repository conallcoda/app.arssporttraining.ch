@props(['group', 'nested' => false])

@php
    $tabKey = implode('-', array_map(fn($item) => $item->name, $group->fieldsets));
    $showWrapper = !$nested && $group->label;
@endphp

@if ($showWrapper)
    <fieldset class="min-w-0 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0 {{ $group->class }}">
        <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            {{ $group->label }}</legend>
        @foreach ($group->headerFields as $field)
            <x-cms.form.field wire:key="field-header-{{ $field->name }}" :field="$field" :prefix="$group->headerPrefix ?? 'data'" />
        @endforeach
        <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
            <flux:tabs :scrollable="$group->scrollableTabs">
                @foreach ($group->fieldsets as $item)
                    <flux:tab :name="$item->name">{{ $item->label }}</flux:tab>
                @endforeach
            </flux:tabs>

            @foreach ($group->fieldsets as $item)
                <flux:tab.panel :name="$item->name">
                    @if ($item instanceof \App\Cms\Form\FormFieldsetGroup)
                        <x-cms.form.fieldset-tabs :group="$item" :nested="true" />
                    @else
                        <x-cms.form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                    @endif
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    </fieldset>
@else
    <div class="space-y-4 {{ $group->class }}">
        @foreach ($group->headerFields as $field)
            <x-cms.form.field wire:key="field-header-{{ $field->name }}" :field="$field" :prefix="$group->headerPrefix ?? 'data'" />
        @endforeach
        <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
            <flux:tabs :scrollable="$group->scrollableTabs">
                @foreach ($group->fieldsets as $item)
                    <flux:tab :name="$item->name">{{ $item->label }}</flux:tab>
                @endforeach
            </flux:tabs>

            @foreach ($group->fieldsets as $item)
                <flux:tab.panel :name="$item->name">
                    @if ($item instanceof \App\Cms\Form\FormFieldsetGroup)
                        <x-cms.form.fieldset-tabs :group="$item" :nested="true" />
                    @else
                        <x-cms.form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                    @endif
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    </div>
@endif
