<x-layouts.guest :title="__('Forgot Password')">
    <form method="POST" action="/forgot-password" class="space-y-6">
        @csrf

        <flux:heading size="lg">{{ __('Forgot Password') }}</flux:heading>

        <flux:text class="text-sm">
            {{ __("Enter your email address and we'll send you a link to reset your password.") }}
        </flux:text>

        @if (session('status'))
            <div class="text-sm text-green-600 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <flux:input
            :label="__('Email')"
            type="email"
            name="email"
            :value="old('email')"
            required
            autofocus
        />

        @if ($errors->any())
            <div class="text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Send Reset Link') }}
        </flux:button>

        <div class="text-center">
            <flux:link href="/login" variant="subtle" class="text-sm">
                {{ __('Back to login') }}
            </flux:link>
        </div>
    </form>
</x-layouts.guest>
