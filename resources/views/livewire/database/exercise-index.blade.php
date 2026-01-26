<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/exercises" :current="request()->is('exercises')">Exercises</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1">Exercises</flux:heading>
    <livewire:database.exercise-list />
</flux:main>
