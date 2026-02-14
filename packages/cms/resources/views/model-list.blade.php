@php
    use Coda\Cms\Display\DisplayFields\Badge as BadgeColumn;
    use Coda\Cms\Display\DisplayFields\Breadcrumb as BreadcrumbColumn;
    use Coda\Cms\Display\DisplayFields\ColorBadge as ColorBadgeColumn;
    use Coda\Cms\Display\DisplayFields\Relationship as RelationshipColumn;
    use Coda\Cms\Display\DisplayFields\TextWithBadgeGroups as TextWithBadgeGroupsColumn;
    use Coda\Cms\Display\DisplayFields\View as ViewColumn;
    use Coda\Cms\Form\Fields\Pillbox as PillboxField;
    use Coda\Cms\Form\Fields\Select as SelectField;
    use Coda\Cms\Form\Fields\Tree;
@endphp

<div>
    @unless ($compact)
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                @if (count($tableFilters) > 0)
                    <flux:button variant="primary" size="sm" icon="list-filter"
                        x-on:click="Flux.modal('{{ $entitySlug }}-filters').show()">
                        Filter
                    </flux:button>

                    @if ($this->hasVisibleFilters())
                        @foreach ($tableFilters as $tableFilter)
                            @php
                                $filterName = $tableFilter->getName();
                                $filterValue = $this->filters[$filterName] ?? null;
                                $filterField = $tableFilter->getField();
                            @endphp
                            @if (array_key_exists($filterName, $this->filters) && $filterField)
                                <flux:input.group>
                                    <flux:input.group.prefix>{{ $tableFilter->getLabel() }}</flux:input.group.prefix>
                                    @if ($filterField instanceof Tree)
                                        <flux:select wire:model.live="filters.{{ $filterName }}" variant="combobox" size="sm" placeholder="{{ $filterField->getPlaceholder() }}">
                                            @foreach ($filterField->flatOptions() as $optionValue => $optionLabel)
                                                <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($filterField instanceof PillboxField)
                                        <flux:select wire:model.live="filters.{{ $filterName }}" variant="listbox" size="sm" multiple searchable placeholder="{{ $filterField->getPlaceholder() }}">
                                            @foreach ($filterField->getOptions() as $optionValue => $optionLabel)
                                                <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($filterField instanceof SelectField)
                                        <flux:select wire:model.live="filters.{{ $filterName }}" size="sm">
                                            @foreach ($filterField->getOptions() as $optionValue => $optionLabel)
                                                <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @else
                                        <flux:input wire:model.live.debounce.300ms="filters.{{ $filterName }}" size="sm" />
                                    @endif
                                    <flux:input.group.suffix>
                                        <button type="button" wire:click="clearFilter('{{ $filterName }}')" class="cursor-pointer">
                                            <flux:icon.x class="size-3" />
                                        </button>
                                    </flux:input.group.suffix>
                                </flux:input.group>
                            @endif
                        @endforeach
                    @endif
                @endif
            </div>
            <div class="flex gap-2">
                @foreach ($this->headerActions as $action)
                    <flux:button variant="{{ $action->variant ?? 'primary' }}" size="sm" icon="{{ $action->icon }}"
                        x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', { title: '{{ $action->modalTitle }}' })">
                        {{ $action->label }}
                    </flux:button>
                @endforeach
            </div>
        </div>
    @endunless

    @if (count($tableFilters) > 0)
        <flux:modal :name="$entitySlug . '-filters'" flyout class="w-80">
            <div class="space-y-6">
                <flux:heading size="lg">Filter</flux:heading>
                <div class="space-y-4">
                    @foreach ($filterFields as $field)
                        <x-cms::form.field :field="$field" prefix="filters" />
                    @endforeach
                </div>
                <div class="flex gap-2 pt-4">
                    <flux:button variant="primary" class="flex-1" wire:click="applyFilters">
                        Apply
                    </flux:button>
                    @if ($this->hasActiveFilters())
                        <flux:button variant="ghost" wire:click="clearFilters">
                            Clear All
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:modal>
    @endif

    @foreach ($this->formModals as $modal)
        @if ($modal['formComponent'])
            @livewire($modal['formComponent'], [
                'name' => $modal['name'],
                'title' => $modal['title'],
                'formDataClass' => $modal['formDataClass'],
                'submitLabel' => $modal['submitLabel'],
            ], key($modal['name']))
        @else
            <livewire:cms.form-modal :name="$modal['name']" :title="$modal['title']" :form-data-class="$modal['formDataClass']"
                :submit-label="$modal['submitLabel']" />
        @endif
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
            @if ($this->hasActiveFilters())
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No
                    {{ Str::plural(strtolower($entityName)) }} found</flux:heading>
            @else
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No
                    {{ Str::plural(strtolower($entityName)) }} yet</flux:heading>
            @endif
        </div>
    @else
        <flux:table :paginate="$this->items" class="table-fixed">
            <flux:table.columns>
                @foreach ($this->columns as $column)
                    @if ($column->sortable)
                        @if ($column->sticky)
                            <flux:table.column sticky sortable :sorted="$this->isSortedBy($column->field)" :direction="$this->currentSortDirection()" wire:click="sortBy('{{ $column->field }}')" class="{{ $column->width }}">
                                {{ $column->getDisplayLabel() }}
                            </flux:table.column>
                        @else
                            <flux:table.column sortable :sorted="$this->isSortedBy($column->field)" :direction="$this->currentSortDirection()" wire:click="sortBy('{{ $column->field }}')" class="{{ $column->width }}">
                                {{ $column->getDisplayLabel() }}
                            </flux:table.column>
                        @endif
                    @else
                        @if ($column->sticky)
                            <flux:table.column sticky class="{{ $column->width }}">
                                {{ $column->getDisplayLabel() }}
                            </flux:table.column>
                        @else
                            <flux:table.column class="{{ $column->width }}">
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

            <flux:table.rows>
                @foreach ($this->items as $model)
                    @php $item = $this->dataFromModel($model); @endphp
                    <flux:table.row wire:key="item-{{ $item->id }}-{{ $this->refreshKey }}">
                        @foreach ($this->columns as $column)
                            <flux:table.cell>
                                @if ($column instanceof RelationshipColumn)
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
                                @elseif ($column instanceof BadgeColumn)
                                    <div class="py-1 flex flex-wrap gap-1">
                                        @if ($column->source)
                                            @php $sourceBadges = $column->getSourceData($item); @endphp
                                            @foreach ($sourceBadges as $badge)
                                                @if (isset($badge['modalField']))
                                                    <flux:badge size="sm" class="cursor-pointer"
                                                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                                            data: {{ Js::from($item->toArray()) }},
                                                            title: 'Edit {{ $entityName }}',
                                                            focusField: '{{ $badge['modalField'] }}'
                                                        })">
                                                        {{ $badge['label'] }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm">
                                                        {{ $badge['label'] }}</flux:badge>
                                                @endif
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
                                @elseif ($column instanceof ColorBadgeColumn)
                                    @php $colorValue = $item->{$column->field}; @endphp
                                    @if ($colorValue)
                                        <flux:badge size="sm" class="{{ \Coda\Cms\Support\ColorPalette::lightBadge($colorValue) }}">
                                            {{ $column->formatValue($colorValue) }}
                                        </flux:badge>
                                    @endif
                                @elseif ($column instanceof TextWithBadgeGroupsColumn)
                                    <div class="py-1 flex flex-wrap items-center gap-1">
                                        @if (property_exists($column, 'modalField') && $column->modalField)
                                            <span class="cursor-pointer hover:underline mr-1"
                                                x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                                    data: {{ Js::from($item->toArray()) }},
                                                    title: 'Edit {{ $entityName }}',
                                                    focusField: '{{ $column->modalField }}'
                                                })">
                                                {{ $item->{$column->field} }}
                                            </span>
                                        @else
                                            <span class="mr-1">{{ $item->{$column->field} }}</span>
                                        @endif
                                        @foreach ($column->getBadgeGroupData($item) as $group)
                                            @foreach ($group['badges'] as $badge)
                                                <flux:badge size="sm" :color="$group['color']">{{ $badge['label'] }}</flux:badge>
                                            @endforeach
                                        @endforeach
                                    </div>
                                @elseif ($column instanceof BreadcrumbColumn)
                                    @php $segments = $column->getSegments($item); @endphp
                                    @if (count($segments) > 0)
                                        <flux:breadcrumbs class="!text-xs">
                                            @foreach ($segments as $segment)
                                                <flux:breadcrumbs.item separator="slash" class="!text-xs">{{ $segment }}</flux:breadcrumbs.item>
                                            @endforeach
                                        </flux:breadcrumbs>
                                    @endif
                                @elseif ($column instanceof ViewColumn)
                                    <div class="py-1 truncate">
                                        <a href="{{ route($column->getViewRouteName(), $model) }}"
                                            class="hover:underline">
                                            @if ($column->prefix)<span
                                                    class="opacity-50">{{ $column->prefix }}</span>@endif
                                            {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
                                        </a>
                                    </div>
                                @elseif (property_exists($column, 'modalField') && $column->modalField)
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
                                    @if (! $action->isVisible($item))
                                        @continue
                                    @endif

                                    @if ($action->isFormModal() && $action->name === 'edit')
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            wire:click="startEdit({{ $item->id }})" />
                                    @elseif ($action->isFormModal())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                                                data: {{ Js::from($item->toArray()) }},
                                                title: '{{ $action->modalTitle }}'
                                            })" />
                                    @elseif ($action->isConfirm())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    @elseif ($action->isAlpineEvent())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            x-on:click="$dispatch('{{ $action->alpineEventName }}', {{ Js::from($action->getAlpineEventData($item)) }})" />
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
