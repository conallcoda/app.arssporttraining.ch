<x-cms::layouts.guest :title="__('Login')">
    @php($showTestLogin = config('cms.user_switching'))

    <div @if($showTestLogin) x-data="{ loginTab: 'normal' }" @endif class="space-y-6">
        @if ($showTestLogin)
            <flux:tabs variant="segmented" class="w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full [&>[data-flux-tabs]]:grid-cols-2">
                <flux:tab name="normal" selected x-on:click="loginTab = 'normal'">{{ __('Normal') }}</flux:tab>
                <flux:tab name="test" x-on:click="loginTab = 'test'">{{ __('Test') }}</flux:tab>
            </flux:tabs>
        @endif

        <div @if($showTestLogin) x-show="loginTab === 'normal'" @endif>
            <form method="POST" action="/login" class="space-y-6">
                @csrf

                <flux:heading size="lg">{{ __('Login') }}</flux:heading>

                <flux:input
                    :label="__('Email')"
                    type="email"
                    name="email"
                    :value="old('email')"
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
        </div>

        @if ($showTestLogin)
            <div x-show="loginTab === 'test'" x-cloak>
                <livewire:cms.login-user-switcher />
            </div>
        @endif
    </div>
</x-cms::layouts.guest>
