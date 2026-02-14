@php $navigation = app(\Coda\Cms\Registry::class)->navigation(); @endphp

@foreach($navigation as $group)
    <flux:sidebar.group expandable icon="{{ $group->icon }}" heading="{{ $group->heading }}" class="grid">
        @foreach($group->items as $item)
            <flux:sidebar.item icon="{{ $item->icon }}" :href="route($item->route)">
                {{ $item->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endforeach
