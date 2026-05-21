@php
    $manualSortingEnabled = $this->manualSortingEnabled();
    $treeColumns = $this->treeColumns;
    $treeExpandableKeys = $this->expandableTreeKeys;
    $treeDefaultExpandedKeys = $this->defaultExpandedTreeKeys;
    $treeShouldRenderExpandCollapseControls = $this->shouldRenderTreeExpandCollapseControls();
@endphp

<div
    x-data="model_tree({
        expandableKeys: {{ Js::from($treeExpandableKeys) }},
        defaultExpandedKeys: {{ Js::from($treeDefaultExpandedKeys) }},
    })"
>
    <div class="flex items-center justify-between mb-3">
        <div></div>
        <div class="flex gap-2">
            @if ($treeShouldRenderExpandCollapseControls)
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="minimize-2"
                    x-on:click="collapseAll()"
                    x-sort:ignore
                    aria-label="Collapse all"
                    title="Collapse all"
                />

                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="expand"
                    x-on:click="expandAll()"
                    x-sort:ignore
                    aria-label="Expand all"
                    title="Expand all"
                />
            @endif

            @if ($this->canCreateRoots())
                <livewire:cms.form-modal
                    :name="$this->createModalName()"
                    :title="$this->createButtonLabel()"
                    :submit-label="'Save'"
                    :max-width="'max-w-4xl'"
                />

                <flux:button
                    variant="primary"
                    size="sm"
                    icon="{{ $this->createButtonIcon() }}"
                    wire:click="openCreateModal"
                >
                    {{ $this->createButtonLabel() }}
                </flux:button>
            @endif

            @foreach ($this->headerActions as $action)
                <flux:button
                    variant="{{ $action->variant ?? 'primary' }}"
                    size="sm"
                    icon="{{ $action->icon }}"
                    wire:click="openHeaderActionModal('{{ $action->name }}')"
                >
                    {{ $action->label }}
                </flux:button>
            @endforeach
        </div>
    </div>

    @foreach ($this->formModals as $modal)
        @if ($modal['formComponent'] ?? false)
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
            <livewire:cms.form-modal
                :name="$modal['name']"
                :title="$modal['title']"
                :form-data-class="$modal['formDataClass']"
                :submit-label="$modal['submitLabel']"
                :max-width="$modal['maxWidth'] ?? 'max-w-sm'"
                :context-data="$modal['contextData'] ?? []"
                :exclude-fields="$modal['excludeFields'] ?? []"
            />
        @endif
    @endforeach

    @foreach ($this->confirmModals as $confirmAction)
        @php
            $confirmModalName = $confirmAction->resolveModalName($entitySlug);
            $resolvedDescription = $this->confirmDescription ?? $confirmAction->confirmDescription;
        @endphp
        <x-cms::confirm-modal
            :name="$confirmModalName"
            :heading="$confirmAction->confirmHeading"
            :description="$resolvedDescription"
            :confirmLabel="$confirmAction->confirmButtonLabel"
            :variant="$confirmAction->confirmButtonVariant ?? 'danger'"
            action="executeConfirmedAction"
        />
    @endforeach

    @if ($this->usesRootFilter() && ! empty($this->rootFilterItems))
        <flux:tabs wire:model.live="selectedRootKey" variant="{{ $this->rootFilterVariant() }}" class="mb-4">
            @foreach ($this->rootFilterItems as $root)
                <flux:tab :name="(string) $root['key']">{{ $root['name'] }}</flux:tab>
            @endforeach
        </flux:tabs>

        @if ($this->showSelectedRootHeading() && $this->selectedRootHeading())
            <div class="flex items-center gap-2 mt-6 mb-4">
                <flux:heading size="xl">{{ $this->selectedRootHeading() }}</flux:heading>
            </div>
        @endif
    @endif

    @if (empty($this->treeItems))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No
                {{ Str::plural(strtolower($entityName)) }} yet</flux:heading>
        </div>
    @elseif (empty($this->displayFlatTreeItems))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ $this->filteredEmptyHeading() }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                @foreach ($treeColumns as $column)
                    <flux:table.column class="{{ trim(($column->widthClass ?? '').' '.($column->headerClass ?? '')) }}">
                        {{ $column->label }}
                    </flux:table.column>
                @endforeach
                <flux:table.column class="w-px"></flux:table.column>
            </flux:table.columns>
            @include('cms::partials.tree-rows', [
                'items' => $this->displayTreeItems,
                'groupKey' => 'root',
                'groupVisibleExpression' => 'true',
                'groupRowKeyPrefix' => 'root',
                'manualSortingEnabled' => $manualSortingEnabled,
                'treeColumns' => $treeColumns,
            ])
        </flux:table>
    @endif
</div>
