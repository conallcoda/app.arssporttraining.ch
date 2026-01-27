<div class="flex gap-6">
    <x-section title="Athletes" class="w-64 shrink-0">
        <div class="flex flex-col gap-1">
            @foreach ($this->users as $userItem)
                <flux:button
                    wire:click="selectUser({{ $userItem->id }})"
                    variant="{{ $user === $userItem->id ? 'primary' : 'ghost' }}"
                    class="justify-start"
                >
                    {{ $userItem->name }}
                </flux:button>
            @endforeach
        </div>
    </x-section>

    <x-section title="Plan" class="flex-1">
        @if ($this->selectedUser)
            <flux:heading size="lg">{{ $this->selectedUser->name }}</flux:heading>
        @else
            <flux:text class="text-zinc-500">Select an athlete to view their plan.</flux:text>
        @endif
    </x-section>
</div>
