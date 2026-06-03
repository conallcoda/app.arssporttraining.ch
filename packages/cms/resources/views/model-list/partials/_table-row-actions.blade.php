<div class="flex gap-0.5 justify-end">
    @foreach ($this->rowActions as $action)
        @if (! $action->isVisible($item))
            @continue
        @endif

        @if ($action->isFormModal() && $action->name === 'edit')
            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                wire:click="startEdit({{ $item->id }})" x-sort:ignore />
        @elseif ($action->isFormModal())
            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                x-on:click="Livewire.dispatch('open-{{ $this->getModalNameForAction($action) }}', {
                    data: {{ Js::from($item->toArray()) }},
                    title: '{{ $action->modalTitle }}'
                })" x-sort:ignore />
        @elseif ($action->isConfirm())
            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                wire:click="confirmAction('{{ $action->name }}', {{ $item->id }})"
                class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400" x-sort:ignore />
        @elseif ($action->isAlpineEvent())
            <flux:button variant="ghost" size="xs" icon="{{ $action->icon }}"
                x-on:click="$dispatch('{{ $action->alpineEventName }}', {{ Js::from($action->getAlpineEventData($item)) }})" x-sort:ignore />
        @elseif ($action->isDirect())
            @php
                $disabled = match ($action->disabledWhen) {
                    'first' => $loop->parent->first,
                    'last' => $loop->parent->last,
                    default => false,
                };
            @endphp
            <flux:button type="button" size="xs" variant="ghost"
                icon="{{ $action->icon }}"
                wire:click="{{ $action->getHandler() }}({{ $item->id }})"
                :disabled="$disabled" x-sort:ignore />
        @endif
    @endforeach

    @if (count($this->rowMenuActions) > 0)
        <flux:dropdown x-sort:ignore>
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
