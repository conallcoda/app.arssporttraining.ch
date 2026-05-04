<x-cms::layouts.guest :title="__('Set Up Account')">
    <form method="POST" action="{{ route('athlete.account-setup.store') }}" class="space-y-6">
        @csrf

        <input type="hidden" name="account_setup_uuid" value="{{ old('account_setup_uuid', $athlete->account_setup_uuid) }}">
        <input type="hidden" name="token" value="{{ old('token', $token) }}">

        <flux:heading size="lg">{{ __('Set Up Account') }}</flux:heading>

        <flux:text class="text-sm">
            {{ __('Choose a password to activate your athlete account.') }}
        </flux:text>

        <flux:text class="text-sm text-zinc-600">
            {{ $athlete->email }}
        </flux:text>

        <flux:input
            :label="__('Password')"
            type="password"
            name="password"
            required
            autofocus
        />

        <flux:input
            :label="__('Confirm Password')"
            type="password"
            name="password_confirmation"
            required
        />

        @if ($errors->any())
            <div class="text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Set Up Account') }}
        </flux:button>
    </form>
</x-cms::layouts.guest>
