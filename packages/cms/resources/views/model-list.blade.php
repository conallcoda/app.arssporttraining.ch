@php
    use Coda\FormKit\Fields\Pillbox as PillboxField;
    use Coda\FormKit\Fields\Select as SelectField;
    use Coda\FormKit\Fields\Tree;
@endphp

<div>
    @if (count($indexTabs) > 0)
        <flux:tabs wire:model.live="selectedTab" variant="segmented" class="mb-4">
            @foreach ($indexTabs as $tab)
                <flux:tab :name="$tab->key" :icon="$tab->icon">{{ $tab->label }}</flux:tab>
            @endforeach
        </flux:tabs>
    @endif

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
                                            @foreach ($filterField->flatOptions($this->filterFieldContextData()) as $optionValue => $optionLabel)
                                                <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($filterField instanceof PillboxField)
                                        <flux:select wire:model.live="filters.{{ $filterName }}" variant="listbox" size="sm" multiple searchable placeholder="{{ $filterField->getPlaceholder() }}">
                                            @foreach ($filterField->getOptions() as $optionValue => $optionLabel)
                                                <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($filterField instanceof SelectField && $filterField->tree)
                                        <flux:select
                                            wire:model.live="filters.{{ $filterName }}"
                                            variant="listbox"
                                            size="sm"
                                            :multiple="$filterField->multiple"
                                            :searchable="$filterField->searchable"
                                            :clearable="$filterField->clearable"
                                            placeholder="{{ $filterField->getPlaceholder() }}"
                                        >
                                            @foreach ($filterField->flatTreeOptions($this->filterFieldContextData()) as $optionValue => $optionLabel)
                                                <flux:select.option value="{{ $optionValue }}">{{ $optionLabel }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @elseif ($filterField instanceof SelectField)
                                        <flux:select
                                            wire:model.live="filters.{{ $filterName }}"
                                            size="sm"
                                            :multiple="$filterField->multiple"
                                            :searchable="$filterField->searchable"
                                            :clearable="$filterField->clearable"
                                        >
                                            @foreach ($filterField->getOptions($this->filterFieldContextData()) as $optionValue => $optionLabel)
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
            <div class="flex gap-2 items-center">
                @if ($this->cardsEnabled && $this->showViewToggle)
                    <flux:button.group>
                        <flux:button size="sm" icon="list"
                            :variant="$viewMode === 'table' ? 'primary' : 'outline'"
                            wire:click="setView('table')" />
                        <flux:button size="sm" icon="layout-grid"
                            :variant="$viewMode === 'cards' ? 'primary' : 'outline'"
                            wire:click="setView('cards')" />
                    </flux:button.group>
                @endif
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
                        <x-form-kit::form.field :field="$field" prefix="filters" :context-data="$this->filterFieldContextData()" />
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
                'showDelete' => true,
                'contextData' => $modal['contextData'] ?? [],
                'excludeFields' => $modal['excludeFields'] ?? [],
            ], key($modal['name']))
        @else
            <livewire:cms.form-modal :name="$modal['name']" :title="$modal['title']" :form-data-class="$modal['formDataClass']"
                :submit-label="$modal['submitLabel']" :max-width="$modal['maxWidth']"
                :context-data="$modal['contextData'] ?? []" :exclude-fields="$modal['excludeFields'] ?? []" />
        @endif
    @endforeach

    <x-cms::confirm-modal
        :name="'confirm-form-delete-' . $entitySlug"
        :heading="__('Delete ' . $entityName . '?')"
        :description="__('You\'re about to delete this ' . strtolower($entityName) . '. This action cannot be reversed.')"
        :confirmLabel="__('Delete')"
        variant="danger"
        action="executeFormDelete"
    />

    @foreach ($this->confirmModals as $confirmAction)
        @php $confirmModalName = $confirmAction->resolveModalName($entitySlug); @endphp
        <x-cms::confirm-modal
            :name="$confirmModalName"
            :heading="$confirmAction->confirmHeading"
            :description="$confirmAction->confirmDescription"
            :confirmLabel="$confirmAction->confirmButtonLabel"
            :variant="$confirmAction->confirmButtonVariant ?? 'danger'"
            action="executeConfirmedAction"
        />
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
        @if ($viewMode === 'cards')
            @include('cms::model-list.partials._cards')
        @else
            @include('cms::model-list.partials._table')
        @endif
    @endif
</div>
