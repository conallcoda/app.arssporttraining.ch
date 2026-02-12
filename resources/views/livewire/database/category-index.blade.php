<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/exercises" :current="request()->is('exercises')">Exercises</flux:navbar.item>
        <flux:navbar.item href="/exercises/categories" :current="request()->is('exercises/categories')">Categories</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">Exercise Categories</flux:heading>
    <livewire:database.category-tree />
</flux:main>
