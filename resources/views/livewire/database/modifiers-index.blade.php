<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/exercises" :current="request()->is('exercises')">Exercises</flux:navbar.item>
        <flux:navbar.item href="/exercises/templates" :current="request()->is('exercises/templates')">Templates</flux:navbar.item>
        <flux:navbar.item href="/exercises/categories" :current="request()->is('exercises/categories')">Categories</flux:navbar.item>
        <flux:navbar.item href="/exercises/equipment" :current="request()->is('exercises/equipment')">Equipment</flux:navbar.item>
        <flux:navbar.item href="/exercises/modifiers" :current="request()->is('exercises/modifiers')">Modifiers</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">Modifiers</flux:heading>
    <livewire:database.modifiers-list />
</flux:main>
