@props([
    'plannedValue',
    'actualValue' => null,
    'mode' => 'planned',
    'field' => null,
])

@php
    $formatValue = static fn (mixed $value): mixed => $field === 'reps'
        ? (\App\Data\Exercise\Settings\RepsSetting::formatAthleteValue($value) ?? $value)
        : $value;
    $displayPlannedValue = $formatValue($plannedValue);
    $displayActualValue = $actualValue === null ? null : $formatValue($actualValue);
@endphp

@if ($mode === 'actual')
    <div class="-mx-3 -my-2 grid grid-cols-2 text-[11px] leading-tight">
        <div class="flex min-w-0 flex-col items-center justify-center px-2 py-2">
            <span class="text-[9px] uppercase tracking-[0.14em] text-zinc-500">{{ __('P') }}</span>
            <span class="truncate text-zinc-200">{{ $displayPlannedValue }}</span>
        </div>
        <div class="flex min-w-0 flex-col items-center justify-center border-l border-zinc-600/80 px-2 py-2">
            <span class="text-[9px] uppercase tracking-[0.14em] text-zinc-500">{{ __('A') }}</span>
            <span class="truncate {{ $actualValue === null || $actualValue === '-' ? 'text-zinc-500' : 'text-zinc-50' }}">
                {{ $displayActualValue ?? '-' }}
            </span>
        </div>
    </div>
@else
    {{ $displayPlannedValue }}
@endif
