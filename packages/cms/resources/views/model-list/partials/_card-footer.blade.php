<div class="mt-auto pt-2 flex gap-0.5 justify-end border-t border-zinc-200 dark:border-zinc-700 -mx-4 -mb-4 px-4 py-2">
    @if (! empty($alternateImageUrl))
        <flux:button
            type="button"
            variant="ghost"
            size="xs"
            icon="rotate-ccw"
            x-on:click="cmsCardImageMode = cmsCardImageMode === 'alternate' ? 'primary' : 'alternate'"
        />
    @endif

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
            <flux:button type="button" size="xs" variant="ghost"
                icon="{{ $action->icon }}"
                wire:click="{{ $action->getHandler() }}({{ $item->id }})" />
        @endif
    @endforeach

    @if (count($this->rowMenuActions) > 0)
        <flux:dropdown>
            <flux:button variant="ghost" size="xs" icon="ellipsis" />
            <flux:menu>
                @foreach ($this->rowMenuActions as $menuAction)
                    @if ($menuAction->isFormModal())
                        <flux:menu.item :icon="$menuAction->icon"
                            wire:click="openActionModal('{{ $menuAction->name }}', {{ $item->id }})">
                            {{ $menuAction->label }}</flux:menu.item>
                    @elseif ($menuAction->isConfirm())
                        <flux:menu.item :icon="$menuAction->icon"
                            wire:click="confirmAction('{{ $menuAction->name }}', {{ $item->id }})">
                            {{ $menuAction->label }}</flux:menu.item>
                    @elseif ($menuAction->isAlpineEvent())
                        <flux:menu.item :icon="$menuAction->icon"
                            x-on:click="$dispatch('{{ $menuAction->alpineEventName }}', {{ Js::from($menuAction->getAlpineEventData($item)) }})">
                            {{ $menuAction->label }}</flux:menu.item>
                    @else
                        <flux:menu.item :icon="$menuAction->icon"
                            wire:click="{{ $menuAction->getHandler() }}({{ $item->id }})">
                            {{ $menuAction->label }}</flux:menu.item>
                    @endif
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
