<div class="flex gap-6">
    <x-section title="Athletes" class="w-64 shrink-0">
        <div class="flex flex-col gap-1">
            <flux:button wire:click="selectUser(0)" variant="{{ $user === 0 ? 'primary' : 'ghost' }}"
                class="justify-start">
                Default Athlete
            </flux:button>

            <div class="mx-3">
                <flux:separator class="my-2" variant="subtle" />
            </div>

            @foreach ($this->users as $userItem)
                <flux:button wire:click="selectUser({{ $userItem->id }})"
                    variant="{{ $user === $userItem->id ? 'primary' : 'ghost' }}" class="justify-start">
                    {{ $userItem->name }}
                </flux:button>
            @endforeach
        </div>
    </x-section>

    <x-section title="Plan" class="flex-1">
        @if ($user === 0)
            <flux:heading size="lg">Default Athlete</flux:heading>

            <div class="mt-6 space-y-4 max-w-lg">
                <div class="flex items-end gap-3">
                    <flux:field class="flex-1">
                        <flux:label>Measured Reps</flux:label>
                        <flux:input.group>
                            <flux:input wire:model.live="measured_reps" type="number" min="1" max="15" step="1" />
                            <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>Measured Weight</flux:label>
                        <flux:input.group>
                            <flux:input wire:model.live="measured_weight" type="number" min="0" step="0.5" />
                            <flux:input.group.suffix>kg</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>Starting 1RM</flux:label>
                        <div class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium">
                            {{ $this->estimatedOneRepMax ? $this->estimatedOneRepMax . 'kg' : 'N/A' }}
                        </div>
                    </flux:field>
                </div>

                <div class="flex items-end gap-3">
                    <flux:field class="flex-[2]">
                        <flux:label>Target Goal</flux:label>
                        <flux:input.group>
                            <flux:input wire:model.live="target_goal" type="number" min="0" max="999" step="1" />
                            <flux:input.group.suffix>%</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>Target 1RM</flux:label>
                        <div class="h-10 flex items-center justify-center rounded-lg bg-green-500/15 text-green-400 font-medium">
                            {{ $this->targetOneRepMax ? $this->targetOneRepMax . 'kg' : 'N/A' }}
                        </div>
                    </flux:field>
                </div>
            </div>
        @elseif ($this->selectedUser)
            <flux:heading size="lg">{{ $this->selectedUser->name }}</flux:heading>

            <div class="mt-6 space-y-4 max-w-lg">
                <div class="flex items-end gap-3">
                    <flux:field class="flex-1">
                        <flux:label>Measured Reps</flux:label>
                        <flux:input.group>
                            <flux:input wire:model.live="measured_reps" type="number" min="1" max="15" step="1" />
                            <flux:input.group.suffix>rep(s)</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>Measured Weight</flux:label>
                        <flux:input.group>
                            <flux:input wire:model.live="measured_weight" type="number" min="0" step="0.5" />
                            <flux:input.group.suffix>kg</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>Starting 1RM</flux:label>
                        <div class="h-10 flex items-center justify-center rounded-lg bg-amber-500/15 text-amber-400 font-medium">
                            {{ $this->estimatedOneRepMax ? $this->estimatedOneRepMax . 'kg' : 'N/A' }}
                        </div>
                    </flux:field>
                </div>

                <div class="flex items-end gap-3">
                    <flux:field class="flex-[2]">
                        <flux:label>Target Goal</flux:label>
                        <flux:input.group>
                            <flux:input wire:model.live="target_goal" type="number" min="0" max="999" step="1"
                                placeholder="{{ $this->getPlaceholder('target_goal') }}" />
                            <flux:input.group.suffix>%</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:field class="flex-1">
                        <flux:label>Target 1RM</flux:label>
                        <div class="h-10 flex items-center justify-center rounded-lg bg-green-500/15 text-green-400 font-medium">
                            {{ $this->targetOneRepMax ? $this->targetOneRepMax . 'kg' : 'N/A' }}
                        </div>
                    </flux:field>
                </div>
            </div>
        @else
            <flux:text class="text-zinc-500">Select an athlete to view their plan.</flux:text>
        @endif
    </x-section>
</div>
