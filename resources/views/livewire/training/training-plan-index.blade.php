<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/training-plans" :current="request()->is('training-plans')">Training Plans</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1">Training Plans</flux:heading>
    <livewire:training.training-plan-list />
</flux:main>
