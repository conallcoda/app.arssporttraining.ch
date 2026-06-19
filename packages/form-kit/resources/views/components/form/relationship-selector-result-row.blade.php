@php
    $resultKey = $field->resolveRecordKey($result);
    $isSelected = $resultKey !== null && in_array((string) $resultKey, $selectedIds, true);
    $resultWireKey = $field->name . '-selector-result-' . ($resultKey ?? $loopIndex);
    $toggleAction = $toggleAction ?? 'toggleRelationshipSelectorItem';
    $sortableRow = $sortableRow ?? false;
    $rowIndex = $rowIndex ?? null;
    $resultAction =
        $toggleAction .
        '(' .
        \Illuminate\Support\Js::from($field->name) .
        ', ' .
        \Illuminate\Support\Js::from($resultKey) .
        ')';
@endphp

<div wire:key="{{ $resultWireKey }}" wire:click="{{ $resultAction }}"
    class="flex cursor-pointer items-stretch px-4 text-sm transition-colors {{ $isSelected ? 'bg-blue-50 dark:bg-blue-500/10 hover:bg-blue-100 dark:hover:bg-blue-500/15' : 'hover:bg-zinc-100/60 dark:hover:bg-zinc-800/60' }}"
    @if ($sortableRow && $rowIndex !== null) data-sort-item-key="{{ $resultKey }}"
        x-sort:item="{{ $resultKey }}" @endif>
    @if ($sortableRow)
        <div class="flex w-8 shrink-0 items-center pl-2">
            <button type="button" x-on:click.stop x-sort:handle
                class="flex cursor-grab items-center justify-center text-zinc-400 active:cursor-grabbing dark:text-zinc-500 dark:hover:text-zinc-300"
                aria-label="Drag to reorder">
                <flux:icon.grip class="size-4" />
            </button>
        </div>
    @endif

    <div class="min-w-0 flex-1 py-4 {{ $sortableRow ? '' : 'pl-2' }}">
        @foreach ($columns as $column)
            @php
                $columnView =
                    $field->resultView && $loop->first
                        ? $field->resultView
                        : 'form-kit::components.form.relationship-selector-cell';
                $columnData =
                    $field->resultView && $loop->first
                        ? ['option' => $result, 'field' => $field]
                        : ['column' => $column, 'record' => $result];
            @endphp
            <div class="{{ $loop->first ? '' : 'mt-2 text-sm text-zinc-600 dark:text-zinc-300' }}">
                @include($columnView, $columnData)
            </div>
        @endforeach
    </div>

    <div class="flex shrink-0 items-center pr-2">
        <div class="flex justify-end">
            <flux:badge size="sm" color="{{ $isSelected ? 'blue' : 'zinc' }}">
                {{ $isSelected ? 'Selected' : 'Select' }}
            </flux:badge>
        </div>
    </div>
</div>
