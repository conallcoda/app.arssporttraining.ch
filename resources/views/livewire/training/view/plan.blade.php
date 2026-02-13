<div class="flex gap-6">
    <x-section title="Plans" class="w-64 shrink-0 sticky top-4 self-start">
        <div class="flex flex-col gap-1">
            <flux:button wire:click="selectUser(null)" variant="{{ $user === null ? 'primary' : 'ghost' }}"
                class="justify-start">
                <span class="flex-1 text-left">Default</span>
            </flux:button>

            <div class="mx-3">
                <flux:separator class="my-2" variant="subtle" />
            </div>

            @foreach ($this->users as $userItem)
                <flux:button wire:key="user-btn-{{ $userItem->id }}" wire:click="selectUser({{ $userItem->id }})"
                    variant="{{ $user === $userItem->id ? 'primary' : 'ghost' }}" class="justify-start">
                    <span class="flex-1 text-left">{{ $userItem->name }}</span>
                </flux:button>
            @endforeach
        </div>
    </x-section>

    <div class="flex-1 space-y-6">
        @if ($user === null)
            <flux:heading size="xl">Default</flux:heading>
        @elseif ($this->selectedUser)
            <flux:heading size="xl">{{ $this->selectedUser->name }}</flux:heading>
        @endif

        <x-section title="Schedule">
            @if ($user === null || $this->selectedUser)
                <div class="space-y-4">
                    <div class="flex items-end gap-3">
                        <flux:field class="flex-1">
                            <flux:label>Start Date</flux:label>
                            <flux:select wire:model.live="startDate">
                                @foreach ($this->weekOptions as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <flux:field class="flex-1">
                            <flux:label>Duration</flux:label>
                            <div class="h-10 flex items-center justify-center rounded-lg bg-blue-500/15 text-blue-400 font-medium">
                                {{ $this->weeks }} weeks
                            </div>
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Programs</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($this->programs->whereIn('id', $this->programIdsFromSchedule) as $program)
                                <flux:badge>{{ $program->name }}</flux:badge>
                            @empty
                                <flux:text class="text-zinc-500">No programs scheduled</flux:text>
                            @endforelse
                        </div>
                    </flux:field>
                </div>
            @else
                <flux:text class="text-zinc-500">Select an athlete to view their plan.</flux:text>
            @endif
        </x-section>
    </div>
</div>
