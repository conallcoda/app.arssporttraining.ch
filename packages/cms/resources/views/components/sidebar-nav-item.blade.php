@props(['item'])

@php
    $hasChildren = count($item->items) > 0;
    $icon = $item->icon !== '' ? $item->icon : null;
@endphp

@if ($hasChildren)
    <flux:sidebar.group expandable :icon="$icon" :heading="$item->label" class="grid">
        @foreach ($item->items as $child)
            <x-cms::sidebar-nav-item :item="$child" />
        @endforeach
    </flux:sidebar.group>
@elseif ($item->route)
    <flux:sidebar.item :icon="$icon" :href="app(\Coda\Cms\Registry::class)->urlForRoute($item->route)">
        {{ $item->label }}
    </flux:sidebar.item>
@endif
