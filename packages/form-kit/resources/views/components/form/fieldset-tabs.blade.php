@props(['group', 'nested' => false, 'statePath' => null])

@php
    $tabKey = implode('-', array_map(fn($item) => $item->name, $group->fieldsets));
    $showWrapper = !$nested && $group->label;
    $defaultTab = $group->fieldsets[0]->name ?? null;
    $wireModel = ! $nested && $statePath ? $statePath : null;

    if ($wireModel && $defaultTab && method_exists($this, 'initializeTabState')) {
        $this->initializeTabState($defaultTab);
    }
@endphp

@if ($showWrapper)
    <fieldset class="min-w-0 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0 {{ $group->class }}">
        <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            {{ $group->label }}</legend>
        @foreach ($group->headerFields as $field)
            <x-form-kit::form.field wire:key="field-header-{{ $field->name }}" :field="$field" :prefix="$group->headerPrefix ?? 'data'" />
        @endforeach
        @if ($wireModel)
            <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
                <flux:tabs wire:model.live="{{ $wireModel }}" :scrollable="$group->scrollableTabs">
                    @foreach ($group->fieldsets as $item)
                        <flux:tab :name="$item->name">{{ $item->label }}</flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($group->fieldsets as $item)
                    <flux:tab.panel :name="$item->name" class="pt-6">
                        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                            <x-form-kit::form.fieldset-tabs :group="$item" :nested="true" :statePath="$statePath" />
                        @else
                            <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                        @endif
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        @else
            <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
                <flux:tabs :scrollable="$group->scrollableTabs">
                    @foreach ($group->fieldsets as $item)
                        <flux:tab :name="$item->name">{{ $item->label }}</flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($group->fieldsets as $item)
                    <flux:tab.panel :name="$item->name" class="pt-6">
                        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                            <x-form-kit::form.fieldset-tabs :group="$item" :nested="true" :statePath="$statePath" />
                        @else
                            <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                        @endif
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        @endif
    </fieldset>
@else
    <div class="space-y-4 {{ $group->class }}">
        @foreach ($group->headerFields as $field)
            <x-form-kit::form.field wire:key="field-header-{{ $field->name }}" :field="$field" :prefix="$group->headerPrefix ?? 'data'" />
        @endforeach
        @if ($wireModel)
            <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
                <flux:tabs wire:model.live="{{ $wireModel }}" :scrollable="$group->scrollableTabs">
                    @foreach ($group->fieldsets as $item)
                        <flux:tab :name="$item->name">{{ $item->label }}</flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($group->fieldsets as $item)
                    <flux:tab.panel :name="$item->name" class="pt-6">
                        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                            <x-form-kit::form.fieldset-tabs :group="$item" :nested="true" :statePath="$statePath" />
                        @else
                            <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                        @endif
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        @else
            <flux:tab.group wire:key="tab-group-{{ $tabKey }}">
                <flux:tabs :scrollable="$group->scrollableTabs">
                    @foreach ($group->fieldsets as $item)
                        <flux:tab :name="$item->name">{{ $item->label }}</flux:tab>
                    @endforeach
                </flux:tabs>

                @foreach ($group->fieldsets as $item)
                    <flux:tab.panel :name="$item->name" class="pt-6">
                        @if ($item instanceof \Coda\FormKit\FormFieldsetGroup)
                            <x-form-kit::form.fieldset-tabs :group="$item" :nested="true" :statePath="$statePath" />
                        @else
                            <x-form-kit::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="false" />
                        @endif
                    </flux:tab.panel>
                @endforeach
            </flux:tab.group>
        @endif
    </div>
@endif
