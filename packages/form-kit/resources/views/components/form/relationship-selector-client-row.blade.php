@props([
    'recordExpr',
    'keyExpr',
    'selectedExpr',
    'clickExpr',
    'sortable' => false,
    'sortKeyExpr' => null,
    'contentPaddingClass' => '',
])

<div
    x-on:click="{{ $clickExpr }}"
    class="flex cursor-pointer items-stretch gap-3 text-sm transition-colors"
    x-bind:class="{{ $selectedExpr }} ? 'bg-blue-50 hover:bg-blue-100 dark:bg-blue-500/10 dark:hover:bg-blue-500/15' : 'hover:bg-zinc-100/60 dark:hover:bg-zinc-800/60'"
    @if ($sortable && $sortKeyExpr)
        data-sort-item-key=""
        x-bind:data-sort-item-key="{{ $sortKeyExpr }}"
        x-sort:item="{{ $sortKeyExpr }}"
    @endif
>
    @if ($sortable)
        <div class="flex w-8 shrink-0 items-center pl-2">
            <button
                type="button"
                x-on:click.stop
                x-sort:handle
                class="flex cursor-grab items-center justify-center text-zinc-400 active:cursor-grabbing dark:text-zinc-500 dark:hover:text-zinc-300"
                aria-label="Drag to reorder"
            >
                <flux:icon.grip class="size-4" />
            </button>
        </div>
    @endif

    <div class="min-w-0 flex-1 py-4 {{ $contentPaddingClass }}">
        <template x-for="(column, columnIndex) in ({{ $recordExpr }}.columns || [])" :key="String({{ $keyExpr }}) + '-column-' + columnIndex">
            <div x-bind:class="columnIndex === 0 ? '' : 'mt-2 text-sm text-zinc-600 dark:text-zinc-300'">
                <template x-if="column.type === 'compact-display'">
                    <div class="space-y-1 py-1">
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100" x-text="column.title || {{ $recordExpr }}.label"></div>
                        <template x-if="(column.badges || []).length > 0">
                            <div class="flex flex-wrap items-center gap-1 text-sm">
                                <template x-for="(badge, badgeIndex) in column.badges" :key="String({{ $keyExpr }}) + '-badge-' + badgeIndex">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-sm" x-bind:class="badge.class" x-text="badge.label"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="column.type !== 'compact-display'">
                    <div class="truncate" x-text="column.text || column.label || {{ $recordExpr }}.label"></div>
                </template>
            </div>
        </template>
    </div>

    <div class="flex shrink-0 items-center py-4 pr-2">
        <div class="flex justify-end">
            <flux:badge size="sm" x-bind:color="{{ $selectedExpr }} ? 'blue' : 'zinc'" x-text="{{ $selectedExpr }} ? 'Selected' : 'Select'"></flux:badge>
        </div>
    </div>
</div>
