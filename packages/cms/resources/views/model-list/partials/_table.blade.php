@php
    $rowCellAlignClass = match ($rowCellVerticalAlign ?? 'middle') {
        'top' => 'align-top',
        'bottom' => 'align-bottom',
        default => 'align-middle',
    };
@endphp

@php
    $manualSortingEnabled = $this->manualSortingEnabled();
    $rowsXData = $manualSortingEnabled ? 'cms_sort_group({ method: \'reorderCurrentPage\' })' : '{}';
    $rowsXSort = $manualSortingEnabled ? 'handleSort($item, $position)' : '';
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

    <flux:table.rows
        x-data="{{ $rowsXData }}"
        x-sort="{{ $rowsXSort }}">
        @foreach ($this->items as $model)
            @php
                $item = $this->dataFromModel($model);
                $rowSortItemKey = $manualSortingEnabled ? (string) $item->id : '';
                $rowSortItem = $manualSortingEnabled ? (string) $item->id : '';
            @endphp
            <flux:table.row
                wire:key="item-{{ $item->id }}-{{ $this->refreshKey }}"
                data-sort-item-key="{{ $rowSortItemKey }}"
                x-sort:item="{{ $rowSortItem }}">
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
                    <div class="flex gap-0.5 justify-end">
                        @foreach ($this->rowActions as $action)
                            @if (! $action->isVisible($item))
                                @continue
                            @endif

                            @if ($action->isFormModal() && $action->name === 'edit')
                                <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                    wire:click="startEdit({{ $item->id }})" x-sort:ignore />
                            @elseif ($action->isFormModal())
                                <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                    x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                                        data: {{ Js::from($item->toArray()) }},
                                        title: '{{ $action->modalTitle }}'
                                    })" x-sort:ignore />
                            @elseif ($action->isConfirm())
                                <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                    wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                                    class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" x-sort:ignore />
                            @elseif ($action->isAlpineEvent())
                                <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                    x-on:click="$dispatch('{{ $action->alpineEventName }}', {{ Js::from($action->getAlpineEventData($item)) }})" x-sort:ignore />
                            @elseif ($action->isDirect())
                                @php
                                    $disabled = match ($action->disabledWhen) {
                                        'first' => $loop->parent->first,
                                        'last' => $loop->parent->last,
                                        default => false,
                                    };
                                @endphp
                                <flux:button type="button" size="xs" variant="ghost"
                                    icon="{{ $action->icon }}"
                                    wire:click="{{ $action->getHandler() }}({{ $item->id }})"
                                    :disabled="$disabled" x-sort:ignore />
                            @endif
                        @endforeach

                        @if (count($this->rowMenuActions) > 0)
                            <flux:dropdown x-sort:ignore>
                                <flux:button variant="ghost" size="xs" icon="ellipsis" />
                                <flux:menu>
                                    @foreach ($this->rowMenuActions as $menuAction)
                                        @if ($menuAction->isFormModal())
                                            <flux:menu.item :icon="$menuAction->icon"
                                                wire:click="openActionModal('{{ $menuAction->name }}', {{ $item->id }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @elseif ($menuAction->isConfirm())
                                            <flux:menu.item :icon="$menuAction->icon"
                                                wire:click="confirmAction('{{ $menuAction->name }}', {{ $item->id }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @elseif ($menuAction->isAlpineEvent())
                                            <flux:menu.item :icon="$menuAction->icon"
                                                x-on:click="$dispatch('{{ $menuAction->alpineEventName }}', {{ Js::from($menuAction->getAlpineEventData($item)) }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @else
                                            <flux:menu.item :icon="$menuAction->icon"
                                                wire:click="{{ $menuAction->getHandler() }}({{ $item->id }})">
                                                {{ $menuAction->label }}</flux:menu.item>
                                        @endif
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>

@include('cms::model-list.partials._pagination')
