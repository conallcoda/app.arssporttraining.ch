<x-slot:navbar>
    <x-athlete-nav />
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1">Athletes</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Manage your athletes</flux:text>
    <livewire:database.athlete-list />
</flux:main>
