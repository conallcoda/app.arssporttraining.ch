<div class="space-y-6">
    <flux:heading size="lg">{{ __('Test Login') }}</flux:heading>

    <flux:field>
        <flux:label>{{ __('User Type') }}</flux:label>
        <flux:select variant="listbox" wire:model.live="selectedType">
            @foreach ($this->userTypeOptions as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>

    <flux:field>
        <flux:label>{{ __('User') }}</flux:label>
        <flux:select variant="listbox" searchable wire:model.live="selectedUserId" placeholder="{{ __('Select user...') }}">
            @foreach ($this->availableUsers as $user)
                <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>

    <flux:button type="button" variant="primary" class="w-full" wire:click="loginAsSelectedUser" :disabled="$selectedUserId === ''">
        {{ __('Login As Selected User') }}
    </flux:button>
</div>
