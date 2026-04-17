@props([
    'tabs' => [],
    'model' => null,
])

@php
    $count = max(1, count($tabs));

    $gridClass = match (true) {
        $count === 1 => '[&>[data-flux-tabs]]:grid-cols-1',
        $count === 2 => '[&>[data-flux-tabs]]:grid-cols-2',
        $count === 3 => '[&>[data-flux-tabs]]:grid-cols-3',
        default => '[&>[data-flux-tabs]]:grid-cols-4',
    };
@endphp

@if ($model)
    <flux:tabs
        variant="segmented"
        wire:model.live="{{ $model }}"
        {{ $attributes->class([
            'athlete-segmented-tabs w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full',
            $gridClass,
        ]) }}
    >
        @foreach ($tabs as $tab)
            @if (($tab['navigate'] ?? false) === true)
                <flux:tab
                    :name="$tab['name']"
                    :icon="$tab['icon'] ?? null"
                    :href="$tab['href'] ?? null"
                    :selected="$tab['selected'] ?? false"
                    wire:navigate
                >
                    {{ $tab['label'] }}
                </flux:tab>
            @else
                <flux:tab
                    :name="$tab['name']"
                    :icon="$tab['icon'] ?? null"
                    :href="$tab['href'] ?? null"
                    :selected="$tab['selected'] ?? false"
                >
                    {{ $tab['label'] }}
                </flux:tab>
            @endif
        @endforeach
    </flux:tabs>
@else
    <flux:tabs
        variant="segmented"
        {{ $attributes->class([
            'athlete-segmented-tabs w-full [&>[data-flux-tabs]]:grid [&>[data-flux-tabs]]:w-full',
            $gridClass,
        ]) }}
    >
        @foreach ($tabs as $tab)
            @if (($tab['navigate'] ?? false) === true)
                <flux:tab
                    :name="$tab['name']"
                    :icon="$tab['icon'] ?? null"
                    :href="$tab['href'] ?? null"
                    :selected="$tab['selected'] ?? false"
                    wire:navigate
                >
                    {{ $tab['label'] }}
                </flux:tab>
            @else
                <flux:tab
                    :name="$tab['name']"
                    :icon="$tab['icon'] ?? null"
                    :href="$tab['href'] ?? null"
                    :selected="$tab['selected'] ?? false"
                >
                    {{ $tab['label'] }}
                </flux:tab>
            @endif
        @endforeach
    </flux:tabs>
@endif
