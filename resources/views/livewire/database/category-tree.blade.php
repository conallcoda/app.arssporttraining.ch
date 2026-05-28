<div x-data="model_tree" class="focus:outline-none">
    <div class="flex items-center justify-between mb-3">
        <div></div>
        <div class="flex gap-2">
            @foreach ($this->headerActions as $action)
                @if ($this->selectedTab)
                    <flux:button variant="{{ $action->variant ?? 'primary' }}" size="sm" icon="{{ $action->icon }}"
                        x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', { title: '{{ $action->modalTitle }}', data: { parentId: {{ (int) $this->selectedTab }} } })">
                        {{ $action->label }}
                    </flux:button>
                @else
                    <flux:button variant="{{ $action->variant ?? 'primary' }}" size="sm" icon="{{ $action->icon }}"
                        x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', { title: '{{ $action->modalTitle }}' })">
                        {{ $action->label }}
                    </flux:button>
                @endif
            @endforeach
        </div>
    </div>

    @foreach ($this->formModals as $modal)
        <livewire:cms.form-modal :name="$modal['name']" :title="$modal['title']" :form-data-class="$modal['formDataClass']"
            :submit-label="$modal['submitLabel']" />
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

    @if (empty($this->rootCategories))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No
                {{ Str::plural(strtolower($entityName)) }} yet</flux:heading>
        </div>
    @else
        <div class="mb-4 max-w-sm">
            <flux:select wire:model.live="selectedTab" variant="listbox" class="w-full">
                @foreach ($this->rootCategories as $root)
                    <flux:select.option value="{{ (string) $root['id'] }}">{{ $root['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->selectedRootName)
            <div class="flex items-center gap-2 mt-6 mb-4">
                <flux:heading size="xl">{{ $this->selectedRootName }}</flux:heading>
            </div>
        @endif

        @if (empty($this->filteredFlatTreeItems))
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-4" />
                <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">{{ __('No subcategories yet') }}</flux:heading>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-full">{{ __('Name') }}</flux:table.column>
                    <flux:table.column class="w-px"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->filteredFlatTreeItems as $item)
                        @php
                            $hasChildren = count($item->children) > 0;
                            $showCondition = empty($item->ancestorIds)
                                ? 'true'
                                : collect($item->ancestorIds)
                                    ->map(fn ($ancestorId) => "isExpanded({$ancestorId})")
                                    ->implode(' && ');
                        @endphp

                        <flux:table.row wire:key="tree-{{ $item->id }}-{{ $this->refreshKey }}"
                            x-show="{{ $showCondition }}">
                            <flux:table.cell>
                                <div class="flex items-center gap-1" style="padding-left: {{ $item->depth * 1.5 }}rem">
                                    @if ($hasChildren)
                                        <button type="button" x-on:click="toggle({{ $item->id }})"
                                            class="p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                            <flux:icon.chevron-down x-show="isExpanded({{ $item->id }})" class="size-4 text-zinc-400" />
                                            <flux:icon.chevron-right x-show="!isExpanded({{ $item->id }})" class="size-4 text-zinc-400" />
                                        </button>
                                    @else
                                        <span class="w-5"></span>
                                    @endif
                                    <span>{{ $item->name }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="text-right">
                                <div class="flex gap-0.5 justify-end">
                                    @foreach ($this->rowActions as $action)
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
                                        @elseif ($action->isDirect())
                                            @php
                                                $disabled = match ($action->disabledWhen) {
                                                    'first' => $item->isFirstSibling,
                                                    'last' => $item->isLastSibling,
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
    @endif
</div>
