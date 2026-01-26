<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/athletes" :current="request()->is('athletes')">Athletes</flux:navbar.item>
        <flux:navbar.item href="/athletes/groups" :current="request()->is('athletes/groups')">Groups</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1">Athlete Groups</flux:heading>
    <livewire:database.athlete-group-list />
</flux:main>
