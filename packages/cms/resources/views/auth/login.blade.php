<x-cms::layouts.guest :title="__('Login')">
    @php
        $showTestLogin = config('cms.user_switching');
        $passkeysEnabled = config('cms.auth.passkeys.enabled');
        $allowPasswordLogin = ! $passkeysEnabled || config('cms.auth.passkeys.allow_password_login', true);
    @endphp

    <div @if($showTestLogin) x-data="{ loginTab: 'normal' }" @endif class="space-y-6">
        @if ($showTestLogin)
            <flux:tabs variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-2">
                <flux:tab name="normal" selected x-on:click="loginTab = 'normal'">{{ __('Normal') }}</flux:tab>
                <flux:tab name="test" x-on:click="loginTab = 'test'">{{ __('Test') }}</flux:tab>
            </flux:tabs>
        @endif

        <div @if($showTestLogin) x-show="loginTab === 'normal'" @endif class="space-y-6">
            <flux:heading size="lg">{{ __('Login') }}</flux:heading>

            @if ($passkeysEnabled)
                <div
                    x-data="cmsPasskeys({ csrfToken: @js(csrf_token()) })"
                    class="space-y-3"
                >
                    <flux:button
                        type="button"
                        variant="primary"
                        icon="finger-print"
                        class="w-full"
                        x-on:click="verify()"
                        x-bind:disabled="isVerifying || !isSupported"
                    >
                        <span x-show="!isVerifying">{{ __('Sign in with passkey') }}</span>
                        <span x-show="isVerifying" x-cloak>{{ __('Waiting for device…') }}</span>
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

                    @if ($allowPasswordLogin)
                        <div class="flex items-center gap-3 py-2">
                            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('or') }}</flux:text>
                            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
                        </div>
                    @endif
                </div>
            @endif

            @if ($allowPasswordLogin)
                <form method="POST" action="/login" class="space-y-6">
                    @csrf

                    <flux:input
                        :label="__('Email')"
                        type="email"
                        name="email"
                        :value="old('email')"
                        autocomplete="{{ $passkeysEnabled ? 'email webauthn' : 'email' }}"
                        required
                        autofocus
                    />

                    <flux:input
                        :label="__('Password')"
                        type="password"
                        name="password"
                        required
                    />

                    <div class="flex items-center justify-between">
                        <flux:checkbox
                            name="remember"
                            :label="__('Remember me')"
                        />

                        <flux:link href="/forgot-password" variant="subtle" class="text-sm">
                            {{ __('Forgot password?') }}
                        </flux:link>
                    </div>

                    @if ($errors->any())
                        <div class="text-sm text-red-600 dark:text-red-400">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Login') }}
                    </flux:button>
                </form>
            @endif
        </div>

        @if ($showTestLogin)
            <div x-show="loginTab === 'test'" x-cloak>
                <livewire:cms.login-user-switcher />
            </div>
        @endif
    </div>
</x-cms::layouts.guest>
