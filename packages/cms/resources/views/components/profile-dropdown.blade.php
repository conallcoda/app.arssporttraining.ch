@auth
    <flux:dropdown position="bottom" align="end">
        <flux:profile initials="{{ auth()->user()->initials() }}" />

        <flux:menu>
            <div class="px-3 py-1.5 text-xs text-zinc-400">
                {{ __('Signed in as') }}: {{ auth()->user()->email }}
            </div>

            <flux:menu.separator />

            @if (in_array(auth()->user()?->type, [\App\Models\Users\UserTypeEnum::Coach, \App\Models\Users\UserTypeEnum::Admin], true))
                <flux:modal.trigger name="coach-settings">
                    <flux:menu.item icon="settings">
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:modal.trigger>
            @endif

            <flux:modal.trigger name="change-password">
                <flux:menu.item icon="lock">
                    {{ __('Change Password') }}
                </flux:menu.item>
            </flux:modal.trigger>

            <form method="POST" action="/logout">
                @csrf
                <flux:menu.item icon="log-out" type="submit">
                    {{ __('Logout') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
@endauth
