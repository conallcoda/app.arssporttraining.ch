@php
    /** @var array<int, array{week: int, session: int, number: int, label: string}> $recordSessions */
    $recordSessions = $recordSessions ?? [];
@endphp

@if (! empty($recordSessions))
    <flux:menu.submenu :heading="__('Edit')" icon="pencil">
        @if (count($recordSessions) === 1)
            @include('components.training.partials.record-menu-actions', [
                'recordSession' => $recordSessions[0],
            ])
        @else
            @foreach ($recordSessions as $recordSession)
                <flux:menu.submenu :heading="$recordSession['label']">
                    @include('components.training.partials.record-menu-actions', [
                        'recordSession' => $recordSession,
                    ])
                </flux:menu.submenu>
            @endforeach
        @endif
    </flux:menu.submenu>
@endif
