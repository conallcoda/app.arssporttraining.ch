@php
    /** @var array<int, array{week: int, session: int, number: int, label: string}> $previewSessions */
    $previewSessions = $previewSessions ?? [];
@endphp
@if (! empty($previewSessions))
    @if (count($previewSessions) === 1)
        <flux:menu.item icon="eye" wire:click="previewSession({{ (int) $previewSessions[0]['week'] }}, {{ (int) $previewSessions[0]['session'] }})">
            {{ __('Preview') }}
        </flux:menu.item>
    @else
        <flux:menu.submenu :heading="__('Preview')" icon="eye">
            @foreach ($previewSessions as $previewSession)
                <flux:menu.item wire:click="previewSession({{ (int) $previewSession['week'] }}, {{ (int) $previewSession['session'] }})">
                    {{ $previewSession['label'] }}
                </flux:menu.item>
            @endforeach
        </flux:menu.submenu>
    @endif
@endif
