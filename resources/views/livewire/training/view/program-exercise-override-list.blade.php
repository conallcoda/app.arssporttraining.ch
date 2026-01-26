<div>
    @if (count($this->items) === 0)
        <flux:text class="text-zinc-500">No exercises in this program.</flux:text>
    @else
        <flux:table class="table-fixed">
            <flux:table.columns>
                @foreach ($this->columns as $column)
                    <flux:table.column class="{{ $column->width }}">{{ $column->getDisplayLabel() }}</flux:table.column>
                @endforeach
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row wire:key="override-{{ $item->id }}-{{ $this->refreshKey }}">
                        @foreach ($this->columns as $column)
                            <flux:table.cell>
                                @if ($column->editable)
                                    @php
                                        $value = $item->{$column->field};
                                        $placeholder = $this->getPlaceholder($item->id, $column->field);
                                        $displayValue = $value ?? $placeholder;
                                        $isOverridden = $value !== null;
                                    @endphp
                                    <div x-data="editable_cell($wire, 'update', [{{ $item->id }}, '{{ $column->field }}'], {{ json_encode($value ?? $placeholder) }}, '{{ $column->suffix ?? '' }}', {{ $column->type === 'number' ? 'true' : 'false' }}, {{ $column->mask ? json_encode($column->mask) : 'null' }}, {{ $column->validationPattern ? json_encode($column->validationPattern) : 'null' }})" @click="startEditing" class="cursor-pointer w-full">
                                        <div x-show="!editing" class="py-1 truncate border border-transparent">
                                            @if ($isOverridden)
                                                <flux:badge size="sm" color="lime">{{ $displayValue }}{{ $column->suffix }}</flux:badge>
                                            @else
                                                <span class="text-zinc-400 dark:text-zinc-500">{{ $displayValue }}{{ $column->suffix }}</span>
                                            @endif
                                        </div>
                                        <input x-show="editing" x-cloak x-ref="input" x-model="value" @click.stop
                                            @blur="save" @keydown="handleKeydown"
                                            @if ($column->mask) @input="applyMask" @endif
                                            type="{{ $column->type }}"
                                            placeholder="{{ $placeholder }}"
                                            @if ($column->type === 'number') step="{{ $column->step ?? 'any' }}"
                                                @if ($column->min !== null) min="{{ $column->min }}" @endif
                                                @if ($column->max !== null) max="{{ $column->max }}" @endif
                                            @endif
                                            :class="isInvalid ? 'border-red-500 ring-1 ring-red-500' : 'border-zinc-300 dark:border-zinc-600 focus:border-zinc-500'"
                                            class="w-full px-2 py-1 text-sm border rounded outline-none focus:ring-0"
                                        />
                                    </div>
                                @else
                                    <div class="py-1 truncate">
                                        {{ $item->{$column->field} }}
                                    </div>
                                @endif
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
