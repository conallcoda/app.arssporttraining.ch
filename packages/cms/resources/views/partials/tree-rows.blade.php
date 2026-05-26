@php
    /** @var array<int, \Coda\Cms\Data\TreeNodeData> $items */
    $items = $items ?? [];
    $treeColumns = $treeColumns ?? $this->treeColumns;
    $hierarchyColumn = collect($treeColumns)->first(fn ($column) => $column->hierarchy) ?? $this->hierarchyColumn;
    $secondaryTreeColumns = collect($treeColumns)
        ->reject(fn ($column) => $column->hierarchy)
        ->values()
        ->all();
    $groupKey = $groupKey ?? 'root';
    $groupVisibleExpression = $groupVisibleExpression ?? 'true';
    $groupRowKeyPrefix = $groupRowKeyPrefix ?? 'root';
    $manualSortingEnabled = $manualSortingEnabled ?? false;
    $groupXData = $manualSortingEnabled
        ? 'cms_sort_group({ method: \'reorderTreeGroup\', groupKey: ' . Js::from($groupKey) . ', grouped: true })'
        : '{}';
    $groupXSort = $manualSortingEnabled ? 'handleSort($item, $position)' : '';
    $groupXSortConfig = $manualSortingEnabled ? '{ forceFallback: true, fallbackOnBody: true }' : '{}';
    $columnCount = count($treeColumns) + 1;
@endphp

<flux:table.rows
    x-data="{{ $groupXData }}"
    x-sort:config="{{ $groupXSortConfig }}"
    x-sort="{{ $groupXSort }}"
    x-show="{{ $groupVisibleExpression }}">
    @foreach ($items as $item)
        @php
            $hasChildren = count($item->children) > 0;
            $isManualSortItem = $manualSortingEnabled && $this->canManuallySortItem($item);
            $rendersChildCards = $this->shouldRenderChildCards($item);
            $hierarchyValue = $hierarchyColumn->resolveValue($item, $this);
            $childGroupKey = $item->key;
            $childGroupVisibleExpression = 'isExpanded(' . Js::from($item->key) . ')';
            $rowWireKey = $groupRowKeyPrefix . '-' . md5((string) ($item->key ?? $item->id)) . '-' . $this->refreshKey;
            $rowSortItem = $isManualSortItem ? (string) $item->key : '';
            $rowSortItemKey = $isManualSortItem ? (string) $item->key : '';
            $rowClass = $rendersChildCards ? $this->childCardParentRowClass() : '';
            $parentLabelClass = $rendersChildCards ? $this->childCardParentLabelClass() : '';
            $parentCellClass = $rendersChildCards ? $this->childCardParentCellClass() : '';
        @endphp

        <flux:table.row
            wire:key="tree-{{ $rowWireKey }}"
            class="{{ $rowClass }}"
            data-sort-item-key="{{ $rowSortItemKey }}"
            data-sort-group-key="{{ $groupKey }}"
            x-sort:item="{{ $rowSortItem }}">
            <flux:table.cell class="{{ trim(($hierarchyColumn->cellClass ?? '').' '.$parentCellClass) }}">
                <div class="flex items-center gap-1" style="padding-left: {{ $item->depth * 1.5 }}rem">
                    @if ($manualSortingEnabled)
                        @if ($isManualSortItem)
                            <button
                                type="button"
                                x-sort:handle
                                class="flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 cursor-grab active:cursor-grabbing"
                                aria-label="Drag to reorder"
                            >
                                <flux:icon.grip class="size-4" />
                            </button>
                        @else
                            <span class="block w-4"></span>
                        @endif
                    @endif
                    @if ($hasChildren)
                        <button type="button" x-on:click="toggle({{ Js::from($item->key) }})"
                            class="p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800" x-sort:ignore>
                            <flux:icon.chevron-down x-show="isExpanded({{ Js::from($item->key) }})" class="size-4 text-zinc-400" />
                            <flux:icon.chevron-right x-show="!isExpanded({{ Js::from($item->key) }})" class="size-4 text-zinc-400" />
                        </button>
                    @else
                        <span class="w-5"></span>
                    @endif
                    <span class="{{ $parentLabelClass }}">{{ $hierarchyValue }}</span>
                </div>
            </flux:table.cell>

            @foreach ($secondaryTreeColumns as $column)
                @php
                    $value = $column->resolveValue($item, $this);
                @endphp
                <flux:table.cell class="{{ trim(($column->cellClass ?? '').' '.$parentCellClass) }}">
                    @if ($column->type === 'color-badge')
                        @if ($value)
                            <flux:badge size="sm" class="{{ \Coda\Cms\Support\ColorPalette::lightBadge((string) $value) }}">
                                {{ ucfirst((string) $value) }}
                            </flux:badge>
                        @endif
                    @elseif ($value instanceof \Illuminate\Contracts\Support\Htmlable)
                        {!! $value->toHtml() !!}
                    @elseif ($value !== null && $value !== '')
                        {{ $value }}
                    @endif
                </flux:table.cell>
            @endforeach

            <flux:table.cell class="text-right {{ $parentCellClass }}">
                <div class="flex gap-0.5 justify-end">
                    @if ($this->canCreateChildrenForItem($item))
                        <flux:button variant="ghost" size="xs" icon="plus"
                            wire:click="openCreateModal({{ Js::from($item->key) }})" x-sort:ignore />
                    @endif

                    @foreach ($this->rowActions as $action)
                        @if ($action->isVisible($item) === false)
                            @continue
                        @endif

                        @if ($action->isFormModal() && $action->name === 'edit')
                            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                wire:click="startEdit({{ Js::from($item->key) }})" x-sort:ignore />
                        @elseif ($action->isFormModal())
                            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                wire:click="openActionModal('{{ $action->name }}', {{ Js::from($item->key) }})" x-sort:ignore />
                        @elseif ($action->isConfirm())
                            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                                wire:click="confirmAction('{{ $action->name }}', {{ Js::from($item->key) }})"
                                class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" x-sort:ignore />
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
                                wire:click="{{ $action->getHandler() }}({{ Js::from($item->key) }})"
                                :disabled="$disabled" x-sort:ignore />
                        @endif
                    @endforeach

                    @if (count($this->rowMenuActions) > 0)
                        <flux:dropdown x-sort:ignore>
                            <flux:button variant="ghost" size="xs" icon="ellipsis" />
                            <flux:menu>
                                @foreach ($this->rowMenuActions as $menuAction)
                                    @if ($menuAction->isVisible($item) === false)
                                        @continue
                                    @endif

                                    <flux:menu.item :icon="$menuAction->icon"
                                        wire:click="openActionModal('{{ $menuAction->name }}', {{ Js::from($item->key) }})">
                                        {{ $menuAction->label }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            </flux:table.cell>
        </flux:table.row>

        @if ($hasChildren && $rendersChildCards)
            @php
                $cardGroupXData = $manualSortingEnabled
                    ? 'cms_sort_group({ method: \'reorderTreeGroup\', groupKey: ' . Js::from($childGroupKey) . ', grouped: true })'
                    : '{}';
                $cardGroupXSort = $manualSortingEnabled ? 'handleSort($item, $position)' : '';
                $cardWidth = $this->childCardWidth();
                $cardMinWidth = $this->childCardMinWidth();
                $cardItemClass = $this->childCardItemClass();
                $cardContainerClass = $cardItemClass
                    ? 'flex flex-wrap gap-4 pt-4 items-start'
                    : 'grid gap-4 pt-4';
                $cardContainerStyle = $cardItemClass
                    ? 'padding-left: '.(($item->depth + 1) * 1.5).'rem'
                    : 'grid-template-columns: repeat(auto-fill, minmax('.$cardMinWidth.', 1fr)); padding-left: '.(($item->depth + 1) * 1.5).'rem';
                $cardBodyClass = $this->childCardBodyClass();
                $cardFooterClass = $this->childCardFooterClass();
                $cardDefinition = $this->childCardDefinition();
            @endphp
            <flux:table.row wire:key="tree-cards-{{ $rowWireKey }}" x-show="{{ $childGroupVisibleExpression }}">
                <flux:table.cell colspan="{{ $columnCount }}" class="pt-0 pb-4">
                    <div
                        class="{{ $cardContainerClass }}"
                        style="{{ $cardContainerStyle }}"
                        x-data="{{ $cardGroupXData }}"
                        x-sort:config="{{ $groupXSortConfig }}"
                        x-sort="{{ $cardGroupXSort }}"
                    >
                        @foreach ($item->children as $child)
                            @php
                                $childSortable = $manualSortingEnabled && $this->canManuallySortItem($child);
                                $childSortKey = $childSortable ? (string) $child->key : '';
                            @endphp
                            <div
                                x-sort:item="{{ $childSortKey }}"
                                data-sort-item-key="{{ $childSortKey }}"
                                data-sort-group-key="{{ $childGroupKey }}"
                                class="{{ $cardItemClass }}"
                            >
                                @include('cms::partials.card-content', [
                                    'definition' => $cardDefinition,
                                    'record' => $child,
                                    'item' => $child,
                                    'wireKey' => 'tree-card-'.$rowWireKey.'-'.md5((string) $child->key).'-'.$this->refreshKey,
                                    'bodyClass' => $cardBodyClass,
                                    'footerView' => 'cms::partials.tree-card-footer',
                                    'footerData' => [
                                        'item' => $child,
                                        'manualSortingEnabled' => $manualSortingEnabled,
                                        'footerClass' => $cardFooterClass,
                                    ],
                                ])
                            </div>
                        @endforeach
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @elseif ($hasChildren)
            @include('cms::partials.tree-rows', [
                'items' => $item->children,
                'groupKey' => $childGroupKey,
                'groupVisibleExpression' => $childGroupVisibleExpression,
                'groupRowKeyPrefix' => $rowWireKey,
                'manualSortingEnabled' => $manualSortingEnabled,
                'treeColumns' => $treeColumns,
            ])
        @endif
    @endforeach
</flux:table.rows>
