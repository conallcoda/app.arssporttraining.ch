<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item :href="route('athlete-index')">{{ __('Athletes') }}</flux:navbar.item>
        <span class="text-zinc-400 dark:text-zinc-500">/</span>
        <flux:navbar.item current>{{ $athlete->forename }} {{ $athlete->surname }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">{{ __('Metrics') }}</flux:heading>

    <livewire:database.athlete-metric-list
        :athleteId="$athlete->id"
        wire:key="athlete-metric-list-{{ $athlete->id }}"
    />
</flux:main>
