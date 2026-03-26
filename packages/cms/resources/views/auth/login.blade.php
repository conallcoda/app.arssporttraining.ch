<x-cms::layouts.guest :title="__('Login')">
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
</x-cms::layouts.guest>
