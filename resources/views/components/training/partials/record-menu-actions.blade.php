@php
    $recordWeek = (int) $recordSession['week'];
    $recordSessionIndex = (int) $recordSession['session'];
    $recordStatus = (string) ($recordSession['status'] ?? 'pending');
@endphp

<flux:menu.item wire:click="requestRecordAction({{ $recordWeek }}, {{ $recordSessionIndex }}, 'edit')">
    {{ __('Edit') }}
</flux:menu.item>
@if ($recordStatus !== 'skipped')
    <flux:menu.item wire:click="requestRecordAction({{ $recordWeek }}, {{ $recordSessionIndex }}, 'skipped')">
        {{ __('Mark as Skipped') }}
    </flux:menu.item>
@endif
@if (in_array($recordStatus, ['pending', 'skipped'], true))
    <flux:menu.item wire:click="requestRecordAction({{ $recordWeek }}, {{ $recordSessionIndex }}, 'completed')">
        {{ __('Mark as Completed') }}
    </flux:menu.item>
@endif
@if ($recordStatus !== 'pending')
    <flux:menu.item wire:click="requestRecordAction({{ $recordWeek }}, {{ $recordSessionIndex }}, 'pending')">
        {{ __('Mark as Pending') }}
    </flux:menu.item>
@endif
