@php
    $routeParameters = request()->route()->parameters();
    $componentKey = $page->name;

    if ($routeParameters !== []) {
        $normalizedParameters = collect($routeParameters)
            ->map(fn (mixed $value) => $value instanceof \Illuminate\Database\Eloquent\Model ? $value->getRouteKey() : $value)
            ->all();

        $componentKey .= ':'.md5(json_encode($normalizedParameters));
    }
@endphp

<x-slot:navbar>
    @if(count($tabs) > 0)
        <x-cms::top-nav>
            @foreach($tabs as $tab)
                <flux:navbar.item
                    :href="app(\Coda\Cms\Registry::class)->urlForRoute($tab->route)"
                    :current="request()->routeIs($tab->route)"
                >
                    {{ $tab->label }}
                </flux:navbar.item>
            @endforeach
        </x-cms::top-nav>
    @endif
</x-slot:navbar>

<flux:main>
    @if (config('cms.breadcrumbs.enabled', false) && ($page->breadcrumbs ?? true) !== false)
        <x-cms::breadcrumbs />
    @endif

    @if ($page->heading !== '')
        <flux:heading size="xl" level="1" class="mb-6">{{ $page->heading }}</flux:heading>
    @endif

    <div id="toolbar" class="{{ $page->heading !== '' ? 'mb-6' : '' }} empty:mb-0"></div>

    @if($page->component)
        @livewire($page->component, $routeParameters, key($componentKey))
    @endif

    @foreach($page->content as $component)
        @livewire($component, [], key($component))
    @endforeach
</flux:main>
