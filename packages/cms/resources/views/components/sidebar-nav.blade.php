@php $navigation = app(\Coda\Cms\Registry::class)->navigation(); @endphp

@foreach($navigation as $group)
    @if($group->heading === '')
        @foreach ($group->items as $item)
            <x-cms::sidebar-nav-item :item="$item" />
        @endforeach
    @elseif(
        count($group->items) === 1
        && $group->items[0]->label === $group->heading
        && count($group->items[0]->items) === 0
        && $group->items[0]->route
    )
        <flux:sidebar.item icon="{{ $group->icon }}" :href="app(\Coda\Cms\Registry::class)->urlForRoute($group->items[0]->route)">
            {{ $group->heading }}
        </flux:sidebar.item>
    @else
        <flux:sidebar.group expandable icon="{{ $group->icon }}" heading="{{ $group->heading }}" class="grid">
            @foreach($group->items as $item)
                <x-cms::sidebar-nav-item :item="$item" />
            @endforeach
        </flux:sidebar.group>
    @endif
@endforeach
