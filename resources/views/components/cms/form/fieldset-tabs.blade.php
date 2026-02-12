@props(['group'])

@if ($group->label)
<fieldset class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 [&>legend+*]:!mt-0">
    <legend class="mb-0 px-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ $group->label }}</legend>
    <flux:tab.group>
        <flux:tabs variant="segmented">
            @foreach ($group->fieldsets as $fieldset)
                <flux:tab :name="$fieldset->name">{{ $fieldset->label }}</flux:tab>
            @endforeach
        </flux:tabs>

        @foreach ($group->fieldsets as $fieldset)
            <flux:tab.panel :name="$fieldset->name">
                <x-cms.form.fieldset
                    :fieldset="$fieldset"
                    :prefix="$fieldset->prefix ?? 'data'"
                    :showLegend="false"
                />
            </flux:tab.panel>
        @endforeach
    </flux:tab.group>
</fieldset>
@else
<flux:tab.group>
    <flux:tabs variant="segmented">
        @foreach ($group->fieldsets as $fieldset)
            <flux:tab :name="$fieldset->name">{{ $fieldset->label }}</flux:tab>
        @endforeach
    </flux:tabs>

    @foreach ($group->fieldsets as $fieldset)
        <flux:tab.panel :name="$fieldset->name">
            <x-cms.form.fieldset
                :fieldset="$fieldset"
                :prefix="$fieldset->prefix ?? 'data'"
                :showLegend="false"
            />
        </flux:tab.panel>
    @endforeach
</flux:tab.group>
@endif
