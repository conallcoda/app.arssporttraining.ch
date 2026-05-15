<div
    x-data="cmsPasskeys({ csrfToken: @js(csrf_token()) })"
    x-on:passkey-registered.window="$wire.dispatch('passkey-registered')"
    class="space-y-6"
>
    <div>
        <flux:heading size="lg">{{ __('Passkeys') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Sign in without a password using a fingerprint, face scan, or device PIN.') }}
        </flux:text>
    </div>

    @if ($passkeys->isEmpty())
        <flux:callout icon="key" color="zinc">
            <flux:callout.heading>{{ __('No passkeys yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Add a passkey to enable passwordless sign-in on this device.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-700">
            @foreach ($passkeys as $passkey)
                <div class="flex items-center justify-between gap-4 px-4 py-3">
                    <div class="min-w-0">
                        <flux:text class="font-medium truncate">{{ $passkey->name }}</flux:text>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Added :date', ['date' => $passkey->created_at?->diffForHumans()]) }}
                            @if ($passkey->last_used_at)
                                · {{ __('Last used :date', ['date' => $passkey->last_used_at->diffForHumans()]) }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="delete({{ $passkey->id }})"
                        wire:confirm="{{ __('Remove this passkey?') }}"
                    />
                </div>
            @endforeach
        </div>
    @endif

    <div
        x-data="{ name: '' }"
        class="space-y-3 border-t border-zinc-200 dark:border-zinc-700 pt-4"
    >
        <flux:input
            x-model="name"
            :label="__('Passkey name')"
            :placeholder="__('e.g. MacBook Pro')"
            x-bind:disabled="isRegistering"
        />
        <flux:button
            type="button"
            variant="primary"
            icon="plus"
            class="w-full"
            x-on:click="register(name)"
            x-bind:disabled="!name || isRegistering || !isSupported"
        >
            <span x-show="!isRegistering">{{ __('Add passkey') }}</span>
            <span x-show="isRegistering" x-cloak>{{ __('Waiting for device…') }}</span>
        </flux:button>
        <p
            x-show="error"
            x-text="error"
            x-cloak
            class="text-sm text-red-600 dark:text-red-400"
        ></p>
        <p
            x-show="!isSupported"
            x-cloak
            class="text-sm text-zinc-500"
        >
            {{ __('This browser does not support passkeys.') }}
        </p>
    </div>
</div>
