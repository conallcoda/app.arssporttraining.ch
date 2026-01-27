<div>
    <flux:modal :name="$modalName" flyout class="w-96"
        x-on:focus-field.window="$nextTick(() => {
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
                <x-flux-form :fields="$this->fields" prefix="data" />
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

    <div class="flex justify-end mb-3">
        <flux:button variant="ghost" size="sm" icon="plus" wire:click="openAddModal">
            Add {{ $entityName }}
        </flux:button>
    </div>

    @if ($this->items->isEmpty())
        <flux:text class="text-zinc-500">No exercises in this program.</flux:text>
    @else
        <flux:table class="table-fixed">
            <flux:table.columns>
                @foreach ($this->columns as $column)
                    <flux:table.column class="{{ $column->width }}">{{ $column->getDisplayLabel() }}</flux:table.column>
                @endforeach
                <flux:table.column class="w-24">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $index => $model)
                    @php $item = $this->dataFromModel($model); @endphp
                    <flux:table.row wire:key="item-{{ $item->id }}-{{ $this->refreshKey }}">
                        @foreach ($this->columns as $column)
                            <flux:table.cell>
                                @if ($column->badge)
                                    <div class="py-1 flex flex-wrap gap-1">
                                        @if ($column->source)
                                            @php $sourceBadges = $column->getSourceData($item); @endphp
                                            @foreach ($sourceBadges as $badge)
                                                <flux:badge size="sm" class="cursor-pointer"
                                                    wire:click="edit({{ $item->id }}, '{{ $badge['modalField'] }}')">
                                                    {{ $badge['label'] }}</flux:badge>
                                            @endforeach
                                        @endif
                                    </div>
                                @elseif ($column->modalField)
                                    <div class="py-1 truncate cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded"
                                        wire:click="edit({{ $item->id }}, '{{ $column->modalField }}')">
                                        {{ $item->{$column->field} }}{{ $column->suffix }}
                                    </div>
                                @else
                                    <div class="py-1 truncate">
                                        {{ $item->{$column->field} }}{{ $column->suffix }}
                                    </div>
                                @endif
                            </flux:table.cell>
                        @endforeach

                        <flux:table.cell class="text-right">
                            @php
                                $isFirst = $loop->first;
                                $isLast = $loop->last;
                            @endphp
                            <div class="flex gap-0.5 justify-end">
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
                                <flux:button variant="ghost" size="xs" icon="trash-2"
                                    wire:click="confirmDelete({{ $item->id }})"
                                    class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
