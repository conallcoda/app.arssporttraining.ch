@auth
    @php
        $settingsMenuItem = config('cms.profile_menu.settings');
        $currentUserType = auth()->user()?->type;
        $currentUserTypeValue = is_object($currentUserType) && property_exists($currentUserType, 'value')
            ? $currentUserType->value
            : $currentUserType;
        $settingsVisible = is_array($settingsMenuItem)
            && (
                ! array_key_exists('user_types', $settingsMenuItem)
                || $settingsMenuItem['user_types'] === null
                || in_array($currentUserTypeValue, $settingsMenuItem['user_types'], true)
            );
    @endphp

    <flux:dropdown position="bottom" align="end">
        <flux:profile initials="{{ auth()->user()->initials() }}" />

        <flux:menu>
            <div class="px-3 py-1.5 text-xs text-zinc-400">
                {{ __('Signed in as') }}: {{ auth()->user()->email }}
            </div>

            <flux:menu.separator />

            @if ($settingsVisible)
                <flux:modal.trigger name="{{ $settingsMenuItem['modal'] }}">
                    <flux:menu.item icon="{{ $settingsMenuItem['icon'] ?? 'settings' }}">
                        {{ __($settingsMenuItem['label'] ?? 'Settings') }}
                    </flux:menu.item>
                </flux:modal.trigger>
            @endif

            <flux:modal.trigger name="change-password">
                <flux:menu.item icon="lock">
                    {{ __('Change Password') }}
                </flux:menu.item>
            </flux:modal.trigger>

            @if (config('cms.auth.passkeys.enabled'))
                <flux:modal.trigger name="manage-passkeys">
                    <flux:menu.item icon="finger-print">
                        {{ __('Passkeys') }}
                    </flux:menu.item>
                </flux:modal.trigger>
            @endif

            <form method="POST" action="/logout">
                @csrf
                <flux:menu.item icon="log-out" type="submit">
                    {{ __('Logout') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
@endauth
