@php
    use Coda\Cms\Display\DisplayFields\Badge as BadgeColumn;
    use Coda\Cms\Display\DisplayFields\Breadcrumb as BreadcrumbColumn;
    use Coda\Cms\Display\DisplayFields\ColorBadge as ColorBadgeColumn;
    use Coda\Cms\Display\DisplayFields\Relationship as RelationshipColumn;
    use Coda\Cms\Display\DisplayFields\TextWithBadgeGroups as TextWithBadgeGroupsColumn;
    use Coda\Cms\Display\DisplayFields\View as ViewColumn;
@endphp

@if ($column instanceof RelationshipColumn)
    @php $relation = $item->{$column->field}; @endphp
    <div class="py-1 flex flex-wrap gap-1">
        @if (is_iterable($relation))
            @foreach ($relation as $index => $related)
                @if ($column->modalField)
                    <flux:badge size="sm" class="cursor-pointer"
                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                            data: {{ Js::from($item->toArray()) }},
                            title: 'Edit {{ $entityName }}',
                            focusField: '{{ $column->modalField }}',
                            focusIndex: {{ $index }}
                        })">
                        {{ data_get($related, $column->displayAttribute) }}
                    </flux:badge>
                @else
                    <flux:badge size="sm">
                        {{ data_get($related, $column->displayAttribute) }}
                    </flux:badge>
                @endif
            @endforeach
        @elseif ($relation)
            @if ($column->modalField)
                <flux:badge size="sm" class="cursor-pointer"
                    x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                        data: {{ Js::from($item->toArray()) }},
                        title: 'Edit {{ $entityName }}',
                        focusField: '{{ $column->modalField }}',
                        focusIndex: 0
                    })">
                    {{ data_get($relation, $column->displayAttribute) }}</flux:badge>
            @else
                <flux:badge size="sm">
                    {{ data_get($relation, $column->displayAttribute) }}</flux:badge>
            @endif
        @endif
    </div>
@elseif ($column instanceof BadgeColumn)
    <div class="py-1 flex flex-wrap gap-1">
        @if ($column->source)
            @php $sourceBadges = $column->getSourceData($item); @endphp
            @foreach ($sourceBadges as $badge)
                @if (isset($badge['modalField']))
                    <flux:badge size="sm" class="cursor-pointer"
                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                            data: {{ Js::from($item->toArray()) }},
                            title: 'Edit {{ $entityName }}',
                            focusField: '{{ $badge['modalField'] }}'
                        })">
                        {{ $badge['label'] }}</flux:badge>
                @else
                    <flux:badge size="sm">
                        {{ $badge['label'] }}</flux:badge>
                @endif
            @endforeach
        @else
            @php
                $badgeValue = $item->{$column->field};
                $badgeValues = is_array($badgeValue) ? $badgeValue : [$badgeValue];
            @endphp
            @foreach ($badgeValues as $index => $val)
                @if ($val !== null && $val !== '')
                    @if ($column->modalField)
                        <flux:badge size="sm" class="cursor-pointer"
                            x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                                data: {{ Js::from($item->toArray()) }},
                                title: 'Edit {{ $entityName }}',
                                focusField: '{{ $column->modalField }}'
                            })">
                            {{ $column->formatValue($val) }}</flux:badge>
                    @else
                        <flux:badge size="sm">{{ $column->formatValue($val) }}
                        </flux:badge>
                    @endif
                @endif
            @endforeach
        @endif
    </div>
@elseif ($column instanceof ColorBadgeColumn)
    @php $colorValue = $item->{$column->field}; @endphp
    @if ($colorValue)
        <flux:badge size="sm" class="{{ \Coda\Cms\Support\ColorPalette::lightBadge($colorValue) }}">
            {{ $column->formatValue($colorValue) }}
        </flux:badge>
    @endif
@elseif ($column instanceof TextWithBadgeGroupsColumn)
    <div class="py-1 flex flex-wrap items-center gap-1">
        @if (property_exists($column, 'modalField') && $column->modalField)
            <span class="cursor-pointer hover:underline mr-1"
                x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                    data: {{ Js::from($item->toArray()) }},
                    title: 'Edit {{ $entityName }}',
                    focusField: '{{ $column->modalField }}'
                })">
                {{ $item->{$column->field} }}
            </span>
        @else
            <span class="mr-1">{{ $item->{$column->field} }}</span>
        @endif
        @foreach ($column->getBadgeGroupData($item) as $group)
            @foreach ($group['badges'] as $badge)
                @if (isset($badge['modalField']))
                    <flux:badge size="sm" :color="$group['color']" class="cursor-pointer"
                        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
                            data: {{ Js::from($item->toArray()) }},
                            title: 'Edit {{ $entityName }}',
                            focusField: '{{ $badge['modalField'] }}'
                        })">
                        {{ $badge['label'] }}
                    </flux:badge>
                @else
                    <flux:badge size="sm" :color="$group['color']">{{ $badge['label'] }}</flux:badge>
                @endif
            @endforeach
        @endforeach
    </div>
@elseif ($column instanceof BreadcrumbColumn)
    @php $segments = $column->getSegments($item); @endphp
    @if (count($segments) > 0)
        <flux:breadcrumbs class="!text-xs">
            @foreach ($segments as $segment)
                <flux:breadcrumbs.item separator="slash" class="!text-xs">{{ $segment }}</flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>
    @endif
@elseif ($column instanceof ViewColumn)
    <div class="py-1 truncate">
        <a href="{{ route($column->getViewRouteName(), $model) }}"
            class="hover:underline">
            @if ($column->prefix)<span
                    class="opacity-50">{{ $column->prefix }}</span>@endif
            {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
        </a>
    </div>
@elseif (property_exists($column, 'modalField') && $column->modalField)
    <div class="py-1 truncate cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded"
        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
            data: {{ Js::from($item->toArray()) }},
            title: 'Edit {{ $entityName }}',
            focusField: '{{ $column->modalField }}'
        })">
        @if ($column->prefix)<span
                class="opacity-50">{{ $column->prefix }}</span>@endif
        {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
    </div>
@else
    <div class="py-1 truncate">
        @if ($column->prefix)<span
                class="opacity-50">{{ $column->prefix }}</span>@endif
        {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
    </div>
@endif
