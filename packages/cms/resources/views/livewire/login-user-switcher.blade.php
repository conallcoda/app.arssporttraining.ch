<div class="space-y-6">
    <flux:heading size="lg">{{ __('Test Login') }}</flux:heading>

    <div class="space-y-4">
        <flux:select wire:model.live="selectedType" variant="listbox" :label="__('User type')">
            @foreach ($this->userTypeOptions as $type => $label)
                <flux:select.option :value="$type">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="selectedUserId" variant="listbox" searchable :label="__('User')">
            @foreach ($this->availableUsers as $user)
                <flux:select.option :value="(string) $user->id">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button type="button" variant="primary" class="w-full" wire:click="loginAsSelectedUser">
            {{ __('Login') }}
        </flux:button>
    </div>
</div>
