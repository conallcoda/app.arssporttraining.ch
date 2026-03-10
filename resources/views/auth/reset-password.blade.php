<x-layouts.guest title="Reset Password">
    <form method="POST" action="/reset-password" class="space-y-6">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <flux:heading size="lg">Reset Password</flux:heading>

        <flux:input
            label="Email"
            type="email"
            name="email"
            :value="old('email', $request->email)"
            required
            autofocus
        />

        <flux:input
            label="Password"
            type="password"
            name="password"
            required
        />

        <flux:input
            label="Confirm Password"
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
            Reset Password
        </flux:button>
    </form>
</x-layouts.guest>
