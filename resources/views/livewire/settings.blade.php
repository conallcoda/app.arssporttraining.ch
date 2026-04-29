<x-slot:navbar>
    <x-cms::top-nav>
        <flux:navbar.item current>{{ __('Settings') }}</flux:navbar.item>
    </x-cms::top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl">{{ __('Settings') }}</flux:heading>
</flux:main>
