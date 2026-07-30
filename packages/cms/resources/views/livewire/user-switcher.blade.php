<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="subtle" size="sm" icon="users">
            {{ auth()->user()->name }}
        </flux:button>

        <flux:menu class="max-h-80 overflow-y-auto">
            @foreach ($this->groupedUsers as $type => $users)
                <flux:menu.heading>{{ ucfirst($type) . 's' }}</flux:menu.heading>

                @foreach ($users as $user)
                    <flux:menu.item
                        wire:click="switchUser({{ $user->id }})"
                        wire:key="switcher-{{ $user->id }}"
                    >
                        {{ $user->name }}
                    </flux:menu.item>
                @endforeach

                @if (!$loop->last)
                    <flux:menu.separator />
                @endif
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
