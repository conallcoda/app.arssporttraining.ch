@php
    $copyOptions = $copyOptions ?? ['from' => [], 'to' => []];
    $previewSessions = $previewSessions ?? [];
    $canReset = (bool) ($canReset ?? false);
    $canToggleGroup = (bool) ($canToggleGroup ?? false);
    $groupExpanded = (bool) ($groupExpanded ?? false);
    $groupIndex = $groupIndex ?? null;
    $showPreview = (bool) ($showPreview ?? false);
    $bucketKey = (string) ($bucketKey ?? '');

    $hasCopyFrom = ! empty($copyOptions['from'] ?? []);
    $hasCopyTo = ! empty($copyOptions['to'] ?? []);
    $hasPreview = $showPreview && ! empty($previewSessions);
    $hasBucketActions = $hasCopyFrom || $hasCopyTo || $hasPreview || $canReset;
    $hasMenuActions = $canToggleGroup || $hasBucketActions;
@endphp

@if ($hasMenuActions)
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
        <flux:menu>
            @if ($canToggleGroup && $groupIndex !== null)
                <flux:menu.item icon="{{ $groupExpanded ? 'chevron-up' : 'chevron-down' }}" wire:click="toggleExpandedGroup({{ (int) $groupIndex }})">
                    {{ $groupExpanded ? __('Collapse group') : __('Expand group') }}
                </flux:menu.item>

                @if ($hasBucketActions)
                    <flux:menu.separator />
                @endif
            @endif

            @if ($hasCopyFrom)
                <flux:menu.submenu :heading="__('Copy From')">
                    @foreach (($copyOptions['from'] ?? []) as $option)
                        <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                            {{ __($option['label']) }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu.submenu>
            @endif

            @if ($hasCopyTo)
                <flux:menu.submenu :heading="__('Copy To')">
                    @if (filled($copyOptions['toAll'] ?? null))
                        <flux:menu.item wire:click="copyDisplayBucketToAll('{{ $copyOptions['toAll']['source'] }}')">
                            {{ __($copyOptions['toAll']['label']) }}
                        </flux:menu.item>
                        <flux:menu.separator />
                    @endif

                    @foreach (($copyOptions['to'] ?? []) as $option)
                        <flux:menu.item wire:click="copyDisplayBucket('{{ $option['source'] }}', '{{ $option['target'] }}')">
                            {{ __($option['label']) }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu.submenu>
            @endif

            @if ($hasPreview)
                @include('components.training.partials.preview-menu-item', [
                    'previewSessions' => $previewSessions,
                ])
            @endif

            @if ($canReset)
                <flux:menu.separator />
                <flux:menu.item icon="rotate-ccw" wire:click="resetDisplayBucket('{{ $bucketKey }}')">
                    {{ __('Reset') }}
                </flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>
@endif
