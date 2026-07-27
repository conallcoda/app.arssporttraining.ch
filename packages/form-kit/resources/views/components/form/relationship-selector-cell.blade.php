@php
    use Coda\Cms\Display\DisplayFields\Badge as BadgeColumn;
    use Coda\Cms\Display\DisplayFields\CompactDisplay as CompactDisplayColumn;
    use Coda\Cms\Display\DisplayFields\ColorBadge as ColorBadgeColumn;
    use Coda\Cms\Display\DisplayFields\Text as TextColumn;

    $value = data_get($record, $column->field);
@endphp

@if ($column instanceof CompactDisplayColumn)
    @php
        $compact = $column->getSourceData($record);
        $title = $compact['title'] ?? null;
        $badges = is_array($compact['badges'] ?? null) ? $compact['badges'] : [];
        $meta = is_array($compact['meta'] ?? null) ? $compact['meta'] : [];
        $allBadges = [...$badges, ...$meta];
    @endphp
    <div class="space-y-1 py-1">
        @if ($title)
            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                {{ $title }}
            </div>
        @endif
        @if ($allBadges !== [])
            <div class="flex flex-wrap items-center gap-1 text-sm">
                @foreach ($allBadges as $badge)
                    @php
                        $badgeClass = isset($badge['color']) && is_string($badge['color']) && $badge['color'] !== ''
                            ? \Coda\Cms\Support\ColorPalette::lightBadge($badge['color'])
                            : '';
                    @endphp
                    <flux:badge size="sm" class="{{ $badgeClass }}">
                        {{ $badge['label'] ?? '' }}
                    </flux:badge>
                @endforeach
            </div>
        @endif
    </div>
@elseif ($column instanceof BadgeColumn)
    @php
        $badges = $column->source ? $column->getSourceData($record) : collect((array) $value)
            ->filter(fn ($badge) => $badge !== null && $badge !== '')
            ->map(fn ($badge) => ['label' => (string) $badge])
            ->values()
            ->all();
    @endphp
    <div class="flex flex-wrap gap-1">
        @foreach ($badges as $badge)
            @php
                $badgeClass = isset($badge['color']) && is_string($badge['color']) && $badge['color'] !== ''
                    ? \Coda\Cms\Support\ColorPalette::lightBadge($badge['color'])
                    : '';
            @endphp
            <flux:badge size="sm" class="{{ $badgeClass }}">
                {{ $badge['label'] }}
            </flux:badge>
        @endforeach
    </div>
@elseif ($column instanceof ColorBadgeColumn)
    @if ($value)
        <flux:badge size="sm" class="{{ \Coda\Cms\Support\ColorPalette::lightBadge((string) $value) }}">
            {{ $column->formatValue($value) }}
        </flux:badge>
    @endif
@elseif ($column instanceof TextColumn)
    <div class="{{ $column->wrap ? 'whitespace-normal break-words' : 'truncate' }}">
        {{ $column->formatValue($value) }}
    </div>
@else
    <div class="truncate">{{ is_scalar($value) ? $value : '' }}</div>
@endif
