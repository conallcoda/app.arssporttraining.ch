@php $navigation = app(\Coda\Cms\Registry::class)->navigation(); @endphp

@foreach($navigation as $group)
    @if(count($group->items) === 1 && $group->items[0]->label === $group->heading)
        <flux:sidebar.item icon="{{ $group->icon }}" :href="route($group->items[0]->route)">
            {{ $group->heading }}
        </flux:sidebar.item>
    @else
        <flux:sidebar.group expandable icon="{{ $group->icon }}" heading="{{ $group->heading }}" class="grid">
            @foreach($group->items as $item)
                <flux:sidebar.item icon="{{ $item->icon }}" :href="route($item->route)">
                    {{ $item->label }}
                </flux:sidebar.item>
            @endforeach
        </flux:sidebar.group>
    @endif
@endforeach
