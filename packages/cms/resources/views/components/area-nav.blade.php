@php
    $registry = app(\Coda\Cms\Registry::class);
    $areas = $registry->topNavigationAreas();
    $currentArea = $registry->currentArea();
@endphp

@if ($areas->isNotEmpty())
    <x-cms::top-nav>
        @foreach ($areas as $area)
            @php $url = $registry->urlForArea($area); @endphp

            @if ($url)
                <flux:navbar.item
                    :href="$url"
                    :current="$currentArea?->key === $area->key"
                >
                    {{ $area->label }}
                </flux:navbar.item>
            @endif
        @endforeach
    </x-cms::top-nav>
@endif
