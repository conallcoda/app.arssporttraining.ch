@props([
    'listExpr',
    'rowExpr',
    'keyExpr',
    'selectedExpr',
    'clickExpr',
    'columnsExpr',
    'buttonVisibleExpr',
    'buttonLabelExpr',
    'buttonIconOnlyExpr' => 'false',
    'buttonClassExpr',
    'buttonActionExpr',
    'itemFieldsExpr' => '[]',
    'clickEnabled' => false,
    'sortableExpr' => 'false',
    'moveUpVisibleExpr' => 'false',
    'moveDownVisibleExpr' => 'false',
    'moveUpDisabledExpr' => 'true',
    'moveDownDisabledExpr' => 'true',
    'moveUpActionExpr' => '',
    'moveDownActionExpr' => '',
])

<div
    x-on:click="{{ $clickExpr !== '' ? $clickExpr : 'void 0' }}"
    class="flex items-stretch gap-3 text-sm transition-colors"
    x-bind:class="[
        ({{ $selectedExpr }} && !isSelectedRowsList({{ $listExpr }})) ? 'bg-blue-50 dark:bg-blue-500/10' : '',
        {{ $clickEnabled ? 'true' : 'false' }} ? 'cursor-pointer' : 'cursor-default'
    ].filter(Boolean).join(' ')"
>
    <template x-if="{{ $moveUpVisibleExpr }} || {{ $moveDownVisibleExpr }}">
        <div class="flex w-8 shrink-0 items-center pl-2">
            <div class="flex flex-col gap-1">
                <button
                    type="button"
                    x-on:click.stop="{{ $moveUpActionExpr }}"
                    x-bind:disabled="{{ $moveUpDisabledExpr }}"
                    class="inline-flex items-center justify-center rounded-md p-1 text-zinc-400 transition hover:bg-white/5 hover:text-zinc-100 disabled:cursor-default disabled:opacity-30 dark:text-zinc-500 dark:hover:text-zinc-300"
                    aria-label="Move up"
                >
                    <flux:icon.chevron-up class="size-4" />
                </button>
                <button
                    type="button"
                    x-on:click.stop="{{ $moveDownActionExpr }}"
                    x-bind:disabled="{{ $moveDownDisabledExpr }}"
                    class="inline-flex items-center justify-center rounded-md p-1 text-zinc-400 transition hover:bg-white/5 hover:text-zinc-100 disabled:cursor-default disabled:opacity-30 dark:text-zinc-500 dark:hover:text-zinc-300"
                    aria-label="Move down"
                >
                    <flux:icon.chevron-down class="size-4" />
                </button>
            </div>
        </div>
    </template>

    <div class="min-w-0 flex-1 py-4" x-bind:class="({{ $moveUpVisibleExpr }} || {{ $moveDownVisibleExpr }}) ? '' : 'pl-2'">
        <template x-for="(column, columnIndex) in {{ $columnsExpr }}" :key="String({{ $keyExpr }}) + '-column-' + columnIndex">
            <div x-bind:class="columnIndex === 0 ? '' : 'mt-2 text-sm text-zinc-600 dark:text-zinc-300'">
                <template x-if="column.type === 'compact-display'">
                    <div class="space-y-1 py-1">
                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100" x-text="column.title || {{ $rowExpr }}.label"></div>
                        <template x-if="(column.badges || []).length > 0">
                            <div class="flex flex-wrap items-center gap-1 text-sm">
                                <template x-for="(badge, badgeIndex) in column.badges" :key="String({{ $keyExpr }}) + '-badge-' + badgeIndex">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-sm" x-bind:class="badge.class || 'bg-zinc-700 text-zinc-100 dark:bg-zinc-700 dark:text-zinc-100'" x-text="badge.label"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="column.type === 'badges'">
                    <div class="flex flex-wrap items-center gap-1 text-sm">
                        <template x-for="(badge, badgeIndex) in column.badges" :key="String({{ $keyExpr }}) + '-flat-badge-' + badgeIndex">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-sm" x-bind:class="badge.class || 'bg-zinc-700 text-zinc-100 dark:bg-zinc-700 dark:text-zinc-100'" x-text="badge.label"></span>
                        </template>
                    </div>
                </template>

                <template x-if="column.type === 'color-badge'">
                    <span class="inline-flex items-center rounded-md px-2 py-1 text-sm" x-bind:class="column.class" x-text="column.label"></span>
                </template>

                <template x-if="!['compact-display', 'badges', 'color-badge'].includes(column.type)">
                    <div class="truncate" x-text="column.text || column.label || {{ $rowExpr }}.label"></div>
                </template>
            </div>
        </template>
    </div>

    <template x-if="{{ $buttonVisibleExpr }}">
        <div class="flex shrink-0 items-center gap-3 py-4 pr-2" x-on:click.stop>
            <template x-for="itemField in {{ $itemFieldsExpr }}" :key="String({{ $keyExpr }}) + '-field-' + itemField.key">
                <div class="relative min-w-20">
                    <select
                        class="w-full appearance-none rounded-md border border-white/20 bg-white/5 px-3 py-2 pr-9 text-sm font-medium leading-5 text-zinc-100 shadow-sm transition focus:outline-none focus:ring-0 dark:bg-white/5"
                        x-on:click.stop
                        x-on:change.stop="updateRowItemField({{ $listExpr }}, row, itemField, $event.target.value)"
                    >
                        <option
                            x-bind:value="''"
                            x-bind:selected="rowItemFieldValue({{ $listExpr }}, row, itemField) === ''"
                            x-text="itemField.placeholder || '-'"
                        ></option>
                        <template x-for="option in (itemField.options || [])" :key="String({{ $keyExpr }}) + '-field-' + itemField.key + '-option-' + option.value">
                            <option
                                x-bind:value="option.value"
                                x-bind:selected="String(option.value) === String(rowItemFieldValue({{ $listExpr }}, row, itemField))"
                                x-text="option.label"
                            ></option>
                        </template>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-zinc-300">
                        <flux:icon.chevron-down class="size-4" />
                    </div>
                </div>
            </template>

            <button
                type="button"
                x-on:click.stop="{{ $buttonActionExpr }}"
                class="flex justify-end"
                x-bind:class="{{ $buttonClassExpr }}"
                x-bind:aria-label="{{ $buttonLabelExpr }}"
                x-bind:title="{{ $buttonLabelExpr }}"
            >
                <template x-if="{{ $buttonIconOnlyExpr }}">
                    <flux:icon.trash-2 class="size-4" />
                </template>
                <template x-if="!{{ $buttonIconOnlyExpr }}">
                    <span x-text="{{ $buttonLabelExpr }}"></span>
                </template>
            </button>
        </div>
    </template>
</div>
