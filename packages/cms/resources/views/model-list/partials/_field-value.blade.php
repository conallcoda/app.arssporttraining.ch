@php
    use Coda\Cms\Display\DisplayFields\Badge as BadgeColumn;
    use Coda\Cms\Display\DisplayFields\Breadcrumb as BreadcrumbColumn;
    use Coda\Cms\Display\DisplayFields\ColorBadge as ColorBadgeColumn;
    use Coda\Cms\Display\DisplayFields\Relationship as RelationshipColumn;
    use Coda\Cms\Display\DisplayFields\Text as TextColumn;
    use Coda\Cms\Display\DisplayFields\TextWithBadgeGroups as TextWithBadgeGroupsColumn;
    use Coda\Cms\Display\DisplayFields\View as ViewColumn;

    $detailsUrl = $this->detailsPageUrl($model);
    $detailsTitleField = $this->detailsTitleField();
    $linksToDetails = $detailsUrl !== null && $detailsTitleField !== null && $column->field === $detailsTitleField;
    $wrapsText = $column instanceof TextColumn && $column->wrap;
    $baseTextClasses = $wrapsText ? 'py-1 whitespace-normal break-words' : 'py-1 truncate';
    $imageValue = $column instanceof TextColumn && $column->imageField
        ? data_get($item, $column->imageField)
        : null;
    $imageUrl = is_array($imageValue) ? ($imageValue['src'] ?? null) : $imageValue;
    $imageSrcset = is_array($imageValue) ? ($imageValue['srcset'] ?? null) : null;
    $imageSizes = is_array($imageValue) ? ($imageValue['sizes'] ?? null) : null;
    $imageWidth = is_array($imageValue) ? ($imageValue['width'] ?? null) : null;
    $imageHeight = is_array($imageValue) ? ($imageValue['height'] ?? null) : null;
    $imageMediaUuid = $column instanceof TextColumn && $column->imageMediaUuidField
        ? data_get($item, $column->imageMediaUuidField)
        : null;
    $imageMediaVersion = $column instanceof TextColumn && $column->imageMediaVersionField
        ? data_get($item, $column->imageMediaVersionField)
        : null;
    $imageFocusPoint = $column instanceof TextColumn && $column->imageFocusPointField
        ? data_get($item, $column->imageFocusPointField)
        : null;
    $hasImage = is_string($imageUrl) && $imageUrl !== '';
    $imageObjectPosition = is_string($imageFocusPoint) && $imageFocusPoint !== '' ? $imageFocusPoint : '50% 50%';
    $formattedTextValue = $column instanceof TextColumn ? $column->formatValue($item->{$column->field}) : null;
@endphp

@php
    if (
        $column instanceof TextColumn
        && $column->imagePreset
        && is_string($imageMediaUuid)
        && $imageMediaUuid !== ''
        && $column->imageWidths !== []
    ) {
        $widths = collect($column->imageWidths)
            ->map(fn (mixed $width) => is_numeric($width) ? (int) $width : null)
            ->filter(fn (?int $width) => $width !== null && $width > 0)
            ->unique()
            ->values();

        $primaryWidth = $widths->first();

        if ($primaryWidth !== null) {
            $imageUrl = route('media.presets.show', [
                'media' => $imageMediaUuid,
                'version' => is_numeric($imageMediaVersion) ? (int) $imageMediaVersion : 0,
                'preset' => $column->imagePreset,
                'w' => $primaryWidth,
            ]);
            $imageSrcset = $widths
                ->map(fn (int $width) => route('media.presets.show', [
                    'media' => $imageMediaUuid,
                    'version' => is_numeric($imageMediaVersion) ? (int) $imageMediaVersion : 0,
                    'preset' => $column->imagePreset,
                    'w' => $width,
                ])." {$width}w")
                ->implode(', ');
            $imageSizes = $column->imageSizes;
            $imageWidth = $primaryWidth;
            $imageHeight = $primaryWidth;
            $hasImage = true;
        }
    }
@endphp

@php
    $textContentHtml = '';

    if ($column instanceof TextColumn) {
        if ($column->prefix) {
            $textContentHtml .= '<span class="opacity-50">'.e($column->prefix).'</span>';
        }

        $textContentHtml .= e($formattedTextValue ?? '');
        $textContentHtml .= e($column->suffix);
    }
@endphp

@php
    $renderTextImage = function () use (
        $column,
        $imageUrl,
        $imageSrcset,
        $imageSizes,
        $imageWidth,
        $imageHeight,
        $formattedTextValue,
        $imageObjectPosition
    ) {
        if ($column instanceof TextColumn && $column->imageSquare) {
            return view('cms::components.avatar', [
                'src' => $imageUrl,
                'srcset' => $imageSrcset,
                'sizes' => $imageSizes,
                'name' => $formattedTextValue,
                'shape' => 'rounded',
                'size' => 'sm',
                'width' => $imageWidth,
                'height' => $imageHeight,
                'objectPosition' => $imageObjectPosition,
                'showInitialsFallback' => $column->imageInitialsFallback,
            ])->render();
        }

        return view('cms::model-list.partials._text-avatar', [
            'imageUrl' => $imageUrl,
            'imageAlt' => $formattedTextValue,
            'showInitialsFallback' => $column instanceof TextColumn ? $column->imageInitialsFallback : true,
        ])->render();
    };
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
    <div class="{{ $baseTextClasses }}">
        <a href="{{ route($column->getViewRouteName(), $model) }}"
            class="hover:underline">
            @if ($column->prefix)<span
                    class="opacity-50">{{ $column->prefix }}</span>@endif
            {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
        </a>
    </div>
@elseif ($linksToDetails && $column instanceof TextColumn && $column->imageField)
    <div class="{{ $baseTextClasses }}">
        <a href="{{ $detailsUrl }}" class="flex items-center gap-3 min-w-0">
            {!! $renderTextImage() !!}
            <span class="min-w-0 truncate hover:underline">
                {!! $textContentHtml !!}
            </span>
        </a>
    </div>
@elseif ($linksToDetails)
    <div class="{{ $baseTextClasses }}">
        <a href="{{ $detailsUrl }}" class="hover:underline">
            @if ($column->prefix)<span
                    class="opacity-50">{{ $column->prefix }}</span>@endif
            {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
        </a>
    </div>
@elseif ($column instanceof TextColumn && $column->imageField && property_exists($column, 'modalField') && $column->modalField)
    <div class="{{ $baseTextClasses }} cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded flex items-center gap-3 min-w-0"
        x-on:click="Livewire.dispatch('open-{{ $this->editModalName }}', {
            data: {{ Js::from($item->toArray()) }},
            title: 'Edit {{ $entityName }}',
            focusField: '{{ $column->modalField }}'
        })">
        {!! $renderTextImage() !!}
        <span class="min-w-0 truncate">
            {!! $textContentHtml !!}
        </span>
    </div>
@elseif ($column instanceof TextColumn && $column->imageField)
    <div class="{{ $baseTextClasses }} flex items-center gap-3 min-w-0">
        {!! $renderTextImage() !!}
        <span class="min-w-0 truncate">
            {!! $textContentHtml !!}
        </span>
    </div>
@elseif (property_exists($column, 'modalField') && $column->modalField)
    <div class="{{ $baseTextClasses }} cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded"
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
    <div class="{{ $baseTextClasses }}">
        @if ($column->prefix)<span
                class="opacity-50">{{ $column->prefix }}</span>@endif
        {{ $column->formatValue($item->{$column->field}) }}{{ $column->suffix }}
    </div>
@endif
