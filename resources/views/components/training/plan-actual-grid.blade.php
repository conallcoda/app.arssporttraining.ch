@props([
    'table' => [],
])

@php
    $sessions = $table['sessions'] ?? [];
    $setCount = (int) ($table['setCount'] ?? 0);
    $setLabel = (string) ($table['setLabel'] ?? 'Set');
    $showSessionDates = (bool) ($table['showSessionDates'] ?? false);
    $setWidthClass = 'w-[10rem] min-w-[10rem]';
    $subcellWidthStyle = 'width: 5rem; min-width: 5rem; max-width: 5rem;';
@endphp

@if ($sessions === [] || $setCount <= 0)
    <div class="text-center text-zinc-500 dark:text-zinc-400 py-8">
        {{ __('No settings configured for this exercise.') }}
    </div>
@else
    <div class="overflow-x-auto text-sm">
        <table class="border-collapse border border-zinc-300 dark:border-zinc-600 table-fixed">
            <thead>
                <tr class="bg-zinc-100 dark:bg-zinc-800">
                    <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12">{{ __('Session') }}</th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                    @for ($setIndex = 0; $setIndex < $setCount; $setIndex++)
                        <th colspan="2" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 whitespace-nowrap {{ $setWidthClass }}">
                            {{ $setLabel }} {{ $setIndex + 1 }}
                        </th>
                    @endfor
                    <th rowspan="2" class="border border-zinc-300 dark:border-zinc-600 px-2 py-2 w-12">
                        <flux:icon.pencil class="mx-auto size-4" />
                    </th>
                </tr>
                <tr class="bg-zinc-100 dark:bg-zinc-800">
                    <th class="border border-zinc-300 dark:border-zinc-600 px-2 py-1"></th>
                    <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-1"></th>
                    @for ($setIndex = 0; $setIndex < $setCount; $setIndex++)
                        <th style="{{ $subcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-[9px] font-medium uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">
                            {{ __('Planned') }}
                        </th>
                        <th style="{{ $subcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-[9px] font-medium uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">
                            {{ __('Actual') }}
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $session)
                    @foreach (($session['rows'] ?? []) as $rowIndex => $row)
                        <tr wire:key="plan-actual-session-{{ $session['week'] }}-{{ $session['session'] }}-row-{{ $row['field'] }}">
                            @if ($rowIndex === 0)
                                <td class="border border-zinc-300 dark:border-zinc-600 px-2 py-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500"
                                    rowspan="{{ count($session['rows'] ?? []) }}">
                                    <div>{{ $session['sessionNumber'] }}</div>
                                    @if ($showSessionDates && filled($session['sessionDateLabel'] ?? null))
                                        <div class="mt-1 text-[10px] font-normal text-zinc-500 dark:text-zinc-400">{{ $session['sessionDateLabel'] }}</div>
                                    @endif
                                    <flux:badge :color="$session['statusColor'] ?? 'zinc'" size="sm" class="mt-1">
                                        {{ $session['statusLabel'] ?? __('Pending') }}
                                    </flux:badge>
                                </td>
                            @endif
                            <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium whitespace-nowrap {{ $row['color'] }}">
                                {{ $row['label'] }}
                            </td>
                            @foreach (($row['cells'] ?? []) as $cell)
                                @php
                                    $plannedKey = 'plan-actual-planned-'.$session['week'].'-'.$session['session'].'-'.$row['field'].'-'.$cell['set'].'-'.md5((string) $cell['planned']);
                                    $actualKey = 'plan-actual-actual-'.$session['week'].'-'.$session['session'].'-'.$row['field'].'-'.$cell['set'].'-'.md5((string) $cell['actual']);
                                    $actualCellClass = $cell['actualColor'] ?? ($row['color'] ?? '');
                                @endphp
                                <td wire:key="{{ $plannedKey }}" style="{{ $subcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $row['color'] }}">
                                    {{ $cell['planned'] }}
                                </td>
                                <td wire:key="{{ $actualKey }}" style="{{ $subcellWidthStyle }}" class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-center {{ $actualCellClass }} {{ $cell['actual'] === '-' ? 'text-zinc-400 dark:text-zinc-500' : '' }}">
                                    {{ $cell['actual'] }}
                                </td>
                            @endforeach
                            @if ($rowIndex === 0)
                                <td class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-center"
                                    rowspan="{{ count($session['rows'] ?? []) }}">
                                    @if ($session['recordable'] ?? false)
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="xs" icon="ellipsis" class="!p-1" />
                                            <flux:menu>
                                                <flux:menu.submenu :heading="__('Edit')" icon="pencil">
                                                    @include('components.training.partials.record-menu-actions', [
                                                        'recordSession' => $session,
                                                    ])
                                                </flux:menu.submenu>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif
