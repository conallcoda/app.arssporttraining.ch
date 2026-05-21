@php
    $rowCellAlignClass = match ($rowCellVerticalAlign ?? 'middle') {
        'top' => 'align-top',
        'bottom' => 'align-bottom',
        default => 'align-middle',
    };
@endphp

@php
    $manualSortingEnabled = $this->manualSortingEnabled();
    $sortableRowsData = $manualSortingEnabled ? "cms_sort_group({ method: 'reorderCurrentPage' })" : null;
    $sortableRowsHandler = $manualSortingEnabled ? 'handleSort($item, $position)' : null;
@endphp

<flux:table class="table-fixed">
    <flux:table.columns>
        @if ($this->manualSortingEnabled())
            <flux:table.column class="w-px"></flux:table.column>
        @endif
        @foreach ($this->columns as $column)
            @if ($column->sortable && ! $this->manualSortingEnabled())
                @if ($column->sticky)
                    <flux:table.column sticky sortable :sorted="$this->isSortedBy($column->getSortField())" :direction="$this->currentSortDirection()" :help-text="$column->getHelpText()" :help-title="$column->getHelpTitle()" wire:click="sortBy('{{ $column->getSortField() }}')" class="{{ $column->width }}">
                        {{ $column->getDisplayLabel() }}
                    </flux:table.column>
                @else
                    <flux:table.column sortable :sorted="$this->isSortedBy($column->getSortField())" :direction="$this->currentSortDirection()" :help-text="$column->getHelpText()" :help-title="$column->getHelpTitle()" wire:click="sortBy('{{ $column->getSortField() }}')" class="{{ $column->width }}">
                        {{ $column->getDisplayLabel() }}
                    </flux:table.column>
                @endif
            @else
                @if ($column->sticky)
                    <flux:table.column sticky :help-text="$column->getHelpText()" :help-title="$column->getHelpTitle()" class="{{ $column->width }}">
                        {{ $column->getDisplayLabel() }}
                    </flux:table.column>
                @else
                    <flux:table.column :help-text="$column->getHelpText()" :help-title="$column->getHelpTitle()" class="{{ $column->width }}">
                        {{ $column->getDisplayLabel() }}
                    </flux:table.column>
                @endif
            @endif
        @endforeach
        <flux:table.column class="w-px">
            @if ($compact)
                @foreach ($this->headerActions as $action)
                    <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                        x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', { title: '{{ $action->modalTitle }}' })">
                        Add
                    </flux:button>
                @endforeach
            @endif
        </flux:table.column>
    </flux:table.columns>

    <flux:table.rows :x-data="$sortableRowsData" :x-sort="$sortableRowsHandler">
        @foreach ($this->items as $model)
            @php
                $item = $this->dataFromModel($model);
                $rowSortItemKey = (string) $item->id;
                $rowSortItem = (string) $item->id;
            @endphp
            <flux:table.row
                wire:key="item-{{ $item->id }}-{{ $this->refreshKey }}"
                :data-sort-item-key="$manualSortingEnabled ? $rowSortItemKey : null"
                :x-sort:item="$manualSortingEnabled ? $rowSortItem : null"
            >
                @if ($this->manualSortingEnabled())
                    <flux:table.cell class="{{ $rowCellAlignClass }}">
                        <button
                            type="button"
                            x-sort:handle
                            class="flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 cursor-grab active:cursor-grabbing"
                            aria-label="Drag to reorder"
                        >
                            <flux:icon.grip class="size-4" />
                        </button>
                    </flux:table.cell>
                @endif

                @foreach ($this->columns as $column)
                    <flux:table.cell class="{{ $rowCellAlignClass }}">
                        @include('cms::model-list.partials._field-value', ['column' => $column, 'item' => $item, 'model' => $model])
                    </flux:table.cell>
                @endforeach

                <flux:table.cell class="text-right {{ $rowCellAlignClass }}">
                    @include('cms::model-list.partials._table-row-actions', ['item' => $item])
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>

@include('cms::model-list.partials._pagination')
