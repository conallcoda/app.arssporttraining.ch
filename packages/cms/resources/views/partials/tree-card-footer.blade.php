@php
    $sortable = $manualSortingEnabled && $this->canManuallySortItem($item);
    $footerClass = $footerClass ?? 'mt-auto pt-2 flex items-center justify-between gap-2 border-t border-zinc-200 dark:border-zinc-700 -mx-4 -mb-4 px-4 py-2';
@endphp

<div class="{{ $footerClass }}">
    <div>
        @if ($sortable)
            <button
                type="button"
                x-sort:handle
                class="flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 cursor-grab active:cursor-grabbing"
                aria-label="Drag to reorder"
            >
                <flux:icon.grip class="size-4" />
            </button>
        @endif
    </div>

    <div class="flex gap-0.5 justify-end">
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
                <flux:button type="button" size="xs" variant="ghost"
                    icon="{{ $action->icon }}"
                    wire:click="{{ $action->getHandler() }}({{ Js::from($item->key) }})"
                    x-sort:ignore />
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
</div>
