<div>
    @unless ($compact)
        <div class="flex justify-end mb-3">
            <flux:button variant="ghost" size="sm" icon="plus" wire:click="openAddModal">
                Add {{ $entityName }}
            </flux:button>
        </div>
    @endunless

    <flux:modal :name="$modalName" flyout class="w-96" x-on:focus-field.window="$nextTick(() => {
        const field = $event.detail.field;
        const index = $event.detail.index;
        let input;
        if (index !== null && index !== undefined) {
            input = $el.querySelector(`[data-field='${field}'][data-index='${index}']`);
        } else {
            input = $el.querySelector(`[data-field='${field}']`);
        }
        if (input) input.focus();
    })">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $this->editingId ? 'Edit' : 'Add' }} {{ $entityName }}</flux:heading>
            <form wire:submit="save" class="space-y-4">
                <x-section title="General">
                    <x-flux-form :fields="$this->fields" prefix="data" />
                </x-section>
                <div class="flex gap-2 pt-4">
                    <flux:button type="submit" variant="primary" class="flex-1">{{ $this->editingId ? 'Save' : 'Add' }} {{ $entityName }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal :name="$deleteModalName" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete {{ $entityName }}?</flux:heading>
                <flux:text class="mt-2">
                    You're about to delete this {{ strtolower($entityName) }}.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="remove">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:table :paginate="$this->items" class="table-fixed">
        <flux:table.columns>
            @foreach ($this->columns as $column)
                @if ($column->sticky)
                    <flux:table.column sticky class="{{ $column->width }}">{{ $column->getDisplayLabel() }}</flux:table.column>
                @else
                    <flux:table.column class="{{ $column->width }}">{{ $column->getDisplayLabel() }}</flux:table.column>
                @endif
            @endforeach
            <flux:table.column class="w-px">
                @if ($compact)
                    <flux:button variant="ghost" size="xs" icon="plus" wire:click="openAddModal">Add</flux:button>
                @endif
            </flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->items as $model)
                @php $item = $this->dataFromModel($model); @endphp
                <flux:table.row wire:key="item-{{ $item->id }}-{{ $this->refreshKey }}">
                    @foreach ($this->columns as $column)
                        <flux:table.cell>
                            @if ($column->editable)
                                <div x-data="editable_cell($wire, 'update', [{{ $item->id }}, '{{ $column->field }}'], {{ json_encode($item->{$column->field}) }}, '{{ $column->suffix ?? '' }}'
                                    {{ $column->type === 'text' ? ', false' : '' }})" @click="startEditing" class="cursor-pointer w-full">
                                    <div x-show="!editing"
                                        x-text="value{{ $column->suffix ? " + '" . $column->suffix . "'" : '' }}"
                                        class="py-1 truncate border border-transparent"></div>
                                    <input x-show="editing" x-cloak x-ref="input" x-model="value" @click.stop
                                        @blur="save" @keydown="handleKeydown" type="{{ $column->type }}"
                                        @if ($column->type === 'number') step="{{ $column->step ?? 'any' }}"
                                            @if ($column->min !== null) min="{{ $column->min }}" @endif
                                        @if ($column->max !== null) max="{{ $column->max }}" @endif
                                        @endif
                                    class="w-full px-2 py-1 text-sm border border-zinc-300 dark:border-zinc-600 rounded outline-none focus:border-zinc-500 focus:ring-0"
                                    />
                                </div>
                            @elseif ($column->type === 'relationship')
                                @php $relation = $item->{$column->field}; @endphp
                                <div class="py-1 flex flex-wrap gap-1">
                                    @if (is_iterable($relation))
                                        @foreach ($relation as $index => $related)
                                            @if ($column->modalField)
                                                <flux:badge size="sm" class="cursor-pointer" wire:click="edit({{ $item->id }}, '{{ $column->modalField }}', {{ $index }})">{{ data_get($related, $column->displayAttribute) }}</flux:badge>
                                            @else
                                                <flux:badge size="sm">{{ data_get($related, $column->displayAttribute) }}</flux:badge>
                                            @endif
                                        @endforeach
                                    @elseif ($relation)
                                        @if ($column->modalField)
                                            <flux:badge size="sm" class="cursor-pointer" wire:click="edit({{ $item->id }}, '{{ $column->modalField }}', 0)">{{ data_get($relation, $column->displayAttribute) }}</flux:badge>
                                        @else
                                            <flux:badge size="sm">{{ data_get($relation, $column->displayAttribute) }}</flux:badge>
                                        @endif
                                    @endif
                                </div>
                            @elseif ($column->badge)
                                <div class="py-1 flex flex-wrap gap-1">
                                    @if ($column->source)
                                        @php $sourceBadges = $column->getSourceData($item); @endphp
                                        @foreach ($sourceBadges as $badge)
                                            <flux:badge size="sm" class="cursor-pointer" wire:click="edit({{ $item->id }}, '{{ $badge['modalField'] }}')">{{ $badge['label'] }}</flux:badge>
                                        @endforeach
                                    @else
                                        @php
                                            $badgeValue = $item->{$column->field};
                                            $badgeValues = is_array($badgeValue) ? $badgeValue : [$badgeValue];
                                        @endphp
                                        @foreach ($badgeValues as $index => $val)
                                            @if ($val !== null && $val !== '')
                                                @if ($column->modalField)
                                                    <flux:badge size="sm" class="cursor-pointer" wire:click="edit({{ $item->id }}, '{{ $column->modalField }}')">{{ $column->formatValue($val) }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm">{{ $column->formatValue($val) }}</flux:badge>
                                                @endif
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            @elseif ($column->type === 'view')
                                <div class="py-1 truncate">
                                    <a href="{{ route($column->getViewRouteName(), $model) }}" class="hover:underline">
                                        @if ($column->prefix)<span class="opacity-50">{{ $column->prefix }}</span>@endif{{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                    </a>
                                </div>
                            @elseif ($column->modalField)
                                <div class="py-1 truncate cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded"
                                    wire:click="edit({{ $item->id }}, '{{ $column->modalField }}')">
                                    @if ($column->prefix)<span class="opacity-50">{{ $column->prefix }}</span>@endif{{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                </div>
                            @else
                                <div class="py-1 truncate">
                                    @if ($column->prefix)<span class="opacity-50">{{ $column->prefix }}</span>@endif{{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                </div>
                            @endif
                        </flux:table.cell>
                    @endforeach

                    <flux:table.cell class="text-right">
                        <div class="flex gap-0.5 justify-end">
                            @if ($sortable)
                                @php
                                    $isFirst = $loop->first;
                                    $isLast = $loop->last;
                                @endphp
                                <flux:button type="button" size="xs" variant="ghost"
                                    wire:click="moveUp({{ $item->id }})"
                                    :disabled="$isFirst">
                                    <x-lucide-chevron-up class="w-4 h-4" />
                                </flux:button>
                                <flux:button type="button" size="xs" variant="ghost"
                                    wire:click="moveDown({{ $item->id }})"
                                    :disabled="$isLast">
                                    <x-lucide-chevron-down class="w-4 h-4" />
                                </flux:button>
                            @endif
                            <flux:button variant="ghost" size="xs" icon="pencil"
                                wire:click="edit({{ $item->id }})" />
                            <flux:button variant="ghost" size="xs" icon="trash-2"
                                wire:click="confirmDelete({{ $item->id }})"
                                class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
