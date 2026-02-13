<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/training/program-categories" :current="request()->is('training/program-categories')">Program Categories</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">Program Categories</flux:heading>
    <livewire:training.program-category-list />
</flux:main>
