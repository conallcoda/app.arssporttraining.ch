<x-slot:navbar>
    @if(count($tabs) > 0)
        <x-top-nav>
            @foreach($tabs as $tab)
                <flux:navbar.item
                    :href="route($tab->route)"
                    :current="request()->routeIs($tab->route)"
                >
                    {{ $tab->label }}
                </flux:navbar.item>
            @endforeach
        </x-top-nav>
    @endif
</x-slot:navbar>

<flux:main>
    <flux:heading size="xl" level="1" class="mb-6">{{ $page->heading }}</flux:heading>

    @foreach($page->content as $component)
        @livewire($component, [], key($component))
    @endforeach
</flux:main>
