<div>
    @unless ($compact)
        <div class="flex justify-end mb-3">
            @foreach ($this->headerActions as $action)
                <flux:button variant="{{ $action->variant ?? 'primary' }}" size="sm" icon="{{ $action->icon }}"
                    x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', { title: '{{ $action->modalTitle }}' })">
                    {{ $action->label }}
                </flux:button>
            @endforeach
        </div>
    @endunless

    @foreach ($this->formModals as $modal)
        <livewire:cms.form-modal :name="$modal['name']" :title="$modal['title']" :form-data-class="$modal['formDataClass']"
            :submit-label="$modal['submitLabel']" />
    @endforeach

    @foreach ($this->confirmModals as $confirmAction)
        @php $confirmModalName = $confirmAction->resolveModalName($entitySlug); @endphp
        <flux:modal :name="$confirmModalName" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $confirmAction->confirmHeading }}</flux:heading>
                    <flux:text class="mt-2">
                        {!! nl2br(e($confirmAction->confirmDescription)) !!}
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="{{ $confirmAction->confirmButtonVariant ?? 'danger' }}"
                        wire:click="executeConfirmedAction">
                        {{ $confirmAction->confirmButtonLabel }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endforeach

    @if ($this->items->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No
                {{ Str::plural(strtolower($entityName)) }} yet</flux:heading>
        </div>
    @else
        <flux:table :paginate="$this->items" class="table-fixed">
            <flux:table.columns>
                @foreach ($this->columns as $column)
                    @if ($column->sticky)
                        <flux:table.column sticky class="{{ $column->width }}">{{ $column->getDisplayLabel() }}
                        </flux:table.column>
                    @else
                        <flux:table.column class="{{ $column->width }}">{{ $column->getDisplayLabel() }}
                        </flux:table.column>
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
                                                    <flux:badge size="sm" class="cursor-pointer"
                                                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                                            data: {{ Js::from($item->toArray()) }},
                                                            title: 'Edit {{ $entityName }}',
                                                            focusField: '{{ $column->modalField }}',
                                                            focusIndex: {{ $index }}
                                                        })">
                                                        {{ data_get($related, $column->displayAttribute) }}
                                                    </flux:badge>
                                                @else
                                                    <flux:badge size="sm">
                                                        {{ data_get($related, $column->displayAttribute) }}
                                                    </flux:badge>
                                                @endif
                                            @endforeach
                                        @elseif ($relation)
                                            @if ($column->modalField)
                                                <flux:badge size="sm" class="cursor-pointer"
                                                    x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                                        data: {{ Js::from($item->toArray()) }},
                                                        title: 'Edit {{ $entityName }}',
                                                        focusField: '{{ $column->modalField }}',
                                                        focusIndex: 0
                                                    })">
                                                    {{ data_get($relation, $column->displayAttribute) }}</flux:badge>
                                            @else
                                                <flux:badge size="sm">
                                                    {{ data_get($relation, $column->displayAttribute) }}</flux:badge>
                                            @endif
                                        @endif
                                    </div>
                                @elseif ($column->badge)
                                    <div class="py-1 flex flex-wrap gap-1">
                                        @if ($column->source)
                                            @php $sourceBadges = $column->getSourceData($item); @endphp
                                            @foreach ($sourceBadges as $badge)
                                                <flux:badge size="sm" class="cursor-pointer"
                                                    x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                                        data: {{ Js::from($item->toArray()) }},
                                                        title: 'Edit {{ $entityName }}',
                                                        focusField: '{{ $badge['modalField'] }}'
                                                    })">
                                                    {{ $badge['label'] }}</flux:badge>
                                            @endforeach
                                        @else
                                            @php
                                                $badgeValue = $item->{$column->field};
                                                $badgeValues = is_array($badgeValue) ? $badgeValue : [$badgeValue];
                                            @endphp
                                            @foreach ($badgeValues as $index => $val)
                                                @if ($val !== null && $val !== '')
                                                    @if ($column->modalField)
                                                        <flux:badge size="sm" class="cursor-pointer"
                                                            x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                                                data: {{ Js::from($item->toArray()) }},
                                                                title: 'Edit {{ $entityName }}',
                                                                focusField: '{{ $column->modalField }}'
                                                            })">
                                                            {{ $column->formatValue($val) }}</flux:badge>
                                                    @else
                                                        <flux:badge size="sm">{{ $column->formatValue($val) }}
                                                        </flux:badge>
                                                    @endif
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                @elseif ($column->type === 'view')
                                    <div class="py-1 truncate">
                                        <a href="{{ route($column->getViewRouteName(), $model) }}"
                                            class="hover:underline">
                                            @if ($column->prefix)<span
                                                    class="opacity-50">{{ $column->prefix }}</span>@endif
                                            {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                        </a>
                                    </div>
                                @elseif ($column->modalField)
                                    <div class="py-1 truncate cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded"
                                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                            data: {{ Js::from($item->toArray()) }},
                                            title: 'Edit {{ $entityName }}',
                                            focusField: '{{ $column->modalField }}'
                                        })">
                                        @if ($column->prefix)<span
                                                class="opacity-50">{{ $column->prefix }}</span>@endif
                                        {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                    </div>
                                @else
                                    <div class="py-1 truncate">
                                        @if ($column->prefix)<span
                                                class="opacity-50">{{ $column->prefix }}</span>@endif
                                        {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                    </div>
                                @endif
                            </flux:table.cell>
                        @endforeach

                        <flux:table.cell class="text-right">
                            <div class="flex gap-0.5 justify-end">
                                @foreach ($this->rowActions as $action)
                                    @if ($action->isFormModal())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                                                data: {{ Js::from($item->toArray()) }},
                                                title: '{{ $action->modalTitle }}'
                                            })" />
                                    @elseif ($action->isConfirm())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    @elseif ($action->isDirect())
                                        @php
                                            $disabled = match ($action->disabledWhen) {
                                                'first' => $loop->parent->first,
                                                'last' => $loop->parent->last,
                                                default => false,
                                            };
                                        @endphp
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="{{ $action->getHandler() }}({{ $item->id }})"
                                            :disabled="$disabled">
                                            <x-dynamic-component :component="'lucide-' . $action->icon"
                                                class="w-4 h-4" />
                                        </flux:button>
                                    @endif
                                @endforeach

                                @if (count($this->rowMenuActions) > 0)
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="xs" icon="ellipsis" />
                                        <flux:menu>
                                            @foreach ($this->rowMenuActions as $menuAction)
                                                <flux:menu.item :icon="$menuAction->icon"
                                                    wire:click="openActionModal('{{ $menuAction->name }}', {{ $item->id }})">
                                                    {{ $menuAction->label }}</flux:menu.item>
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
    @endif
</div>
