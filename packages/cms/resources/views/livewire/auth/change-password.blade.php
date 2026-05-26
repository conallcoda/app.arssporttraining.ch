<form wire:submit="save" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Change Password') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Update your account password.') }}</flux:text>
    </div>

    <flux:input wire:model="current_password" label="{{ __('Current Password') }}" type="password" />
    <flux:input wire:model="password" label="{{ __('New Password') }}" type="password" />
    <flux:input wire:model="password_confirmation" label="{{ __('Confirm Password') }}" type="password" />

    <div class="flex gap-2">
        <flux:spacer />
        <flux:modal.close>
            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
        </flux:modal.close>
        <flux:button type="submit" variant="primary">{{ __('Change Password') }}</flux:button>
    </div>
</form>
