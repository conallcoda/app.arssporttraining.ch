<div class="flex flex-col h-full">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <flux:select wire:model.live="system" class="w-72">
                @foreach ($systems as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div
        x-data="org_chart_parse({
            trees: {{ Js::from($trees) }},
            errors: {{ Js::from($errors) }},
            initial: '{{ array_key_first($tabs) }}',
        })"
        wire:key="docs-{{ $system }}"
        class="flex flex-col flex-1 min-h-0"
    >
        <div class="flex items-center justify-between mb-4">
            <flux:tabs variant="segmented">
                @foreach ($tabs as $key => $label)
                    <flux:tab name="{{ $key }}" :selected="$loop->first" x-on:click="switchTab('{{ $key }}')">{{ $label }}</flux:tab>
                @endforeach
            </flux:tabs>

            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="subtle" icon="expand" x-on:click="expandAll" />
                <flux:button size="sm" variant="subtle" icon="minimize-2" x-on:click="collapseAll" />
                <flux:button size="sm" variant="subtle" icon="scan" x-on:click="resetView" />
                <flux:separator vertical class="mx-1 h-4" />
                <flux:button
                    size="sm"
                    variant="subtle"
                    icon="download"
                    x-on:click="exportPdf"
                    x-bind:disabled="exporting"
                    x-bind:class="exporting && 'opacity-50 cursor-wait'"
                />
            </div>
        </div>

        <div
            x-show="! errors[activeTree]"
            class="relative flex-1 min-h-0 overflow-hidden rounded-2xl border border-zinc-800"
        >
            <div x-ref="diagram" class="org-chart-canvas h-full w-full"></div>
        </div>

        <div
            x-show="errors[activeTree]"
            x-cloak
            class="rounded-2xl border border-red-400/30 bg-red-950/20 p-5 text-sm text-red-100"
        >
            <div class="font-medium">Tree parsing error</div>
            <div class="mt-2 text-red-100/80" x-text="errors[activeTree]"></div>
        </div>
    </div>
</div>
