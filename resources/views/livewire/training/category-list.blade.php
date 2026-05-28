@php
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
                    aria-label="Collapse all"
                    title="Collapse all"
                />

                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="expand"
                    x-on:click="expandAll()"
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

    @if (empty($this->treeItems))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No
                {{ \Illuminate\Support\Str::plural(strtolower($entityName)) }} yet</flux:heading>
        </div>
    @elseif (empty($this->displayFlatTreeItems))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ $this->filteredEmptyHeading() }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-1/3">{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Short Name') }}</flux:table.column>
                <flux:table.column>{{ __('Color') }}</flux:table.column>
                <flux:table.column class="w-40">{{ __('Last Changed') }}</flux:table.column>
                <flux:table.column class="w-px"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->displayFlatTreeItems as $item)
                    @php
                        $hasChildren = count($item->children) > 0;
                        $showCondition = empty($item->ancestorKeys)
                            ? 'true'
                            : collect($item->ancestorKeys)
                                ->map(fn ($ancestorKey) => 'isExpanded('.Js::from($ancestorKey).')')
                                ->implode(' && ');
                        $color = data_get($item->formData, 'color');
                        $updatedAt = data_get($item->formData, 'updatedAt');
                        $updatedAtLabel = null;

                        if ($updatedAt instanceof \Carbon\CarbonInterface) {
                            $updatedAtLabel = $updatedAt->diffForHumans();
                        } elseif (is_string($updatedAt) && $updatedAt !== '') {
                            $updatedAtLabel = \Carbon\Carbon::parse($updatedAt)->diffForHumans();
                        }
                    @endphp

                    <flux:table.row
                        wire:key="training-category-tree-{{ $item->id }}-{{ $this->refreshKey }}"
                        x-show="{{ $showCondition }}"
                    >
                        <flux:table.cell>
                            <div class="flex items-center gap-1" style="padding-left: {{ $item->depth * 1.5 }}rem">
                                @if ($hasChildren)
                                    <button type="button" x-on:click="toggle({{ Js::from($item->key) }})"
                                        class="p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        <flux:icon.chevron-down x-show="isExpanded({{ Js::from($item->key) }})" class="size-4 text-zinc-400" />
                                        <flux:icon.chevron-right x-show="!isExpanded({{ Js::from($item->key) }})" class="size-4 text-zinc-400" />
                                    </button>
                                @else
                                    <span class="w-5"></span>
                                @endif

                                <span>{{ $item->name }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if (data_get($item->formData, 'parentId') === null)
                                {{ data_get($item->formData, 'shortName') }}
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($color)
                                <flux:badge size="sm" class="{{ \Coda\Cms\Support\ColorPalette::lightBadge((string) $color) }}">
                                    {{ ucfirst((string) $color) }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $updatedAtLabel }}
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <div class="flex gap-0.5 justify-end">
                                @if ($this->canCreateChildrenForItem($item))
                                    <flux:button variant="ghost" size="xs" icon="plus"
                                        wire:click="openCreateModal({{ Js::from($item->key) }})" />
                                @endif

                                @foreach ($this->rowActions as $action)
                                    @if ($action->isFormModal() && $action->name === 'edit')
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            wire:click="startEdit({{ $item->id }})" />
                                    @elseif ($action->isFormModal())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            wire:click="openActionModal('{{ $action->name }}', {{ $item->id }})" />
                                    @elseif ($action->isConfirm())
                                        <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                            wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" />
                                    @elseif ($action->isDirect())
                                        @php
                                            $disabled = match ($action->disabledWhen) {
                                                'first' => $item->isFirstSibling,
                                                'last' => $item->isLastSibling,
                                                default => false,
                                            };
                                        @endphp
                                        <flux:button type="button" size="xs" variant="ghost"
                                            icon="{{ $action->icon }}"
                                            wire:click="{{ $action->getHandler() }}({{ $item->id }})"
                                            :disabled="$disabled" />
                                    @endif
                                @endforeach

                                @if (count($this->rowMenuActions) > 0)
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="xs" icon="ellipsis" />
                                        <flux:menu>
                                            @foreach ($this->rowMenuActions as $menuAction)
                                                <flux:menu.item :icon="$menuAction->icon"
                                                    wire:click="openActionModal('{{ $menuAction->name }}', {{ $item->id }})">
                                                    {{ $menuAction->label }}
                                                </flux:menu.item>
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
