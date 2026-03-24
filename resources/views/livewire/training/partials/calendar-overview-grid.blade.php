<div class="overflow-x-auto">
    <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600">
        <thead>
            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                <th rowspan="3"
                    class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left min-w-[180px]">
                </th>
                @foreach ($this->months as $month)
                    <th colspan="{{ $month['colspan'] }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-2 py-1.5 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800">
                        {{ $month['label'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                @foreach ($this->weeks as $week)
                    <th colspan="{{ $week['colspan'] }}"
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 {{ $week['week'] % 2 !== 0 ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '' }}">
                        W{{ $week['week'] }}
                    </th>
                @endforeach
            </tr>
            <tr class="bg-zinc-100 dark:bg-zinc-800">
                @foreach ($this->days as $day)
                    <th
                        class="border-r border-b border-zinc-300 dark:border-zinc-600 px-1 py-2 text-center min-w-[40px] {{ $day['isToday'] ? 'bg-blue-100 dark:bg-blue-900/30' : ($day['oddWeek'] ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '') }}">
                        <div class="text-[10px] leading-tight">{{ $day['label'] }}</div>
                        <div class="font-medium text-xs">{{ $day['day'] }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        @foreach ($this->overviewData as $groupRow)
            <tbody x-data="{ expanded: false }" wire:key="overview-group-{{ $groupRow['group']->id }}">
                <tr>
                    <td
                        class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap min-w-[180px] bg-zinc-100 dark:bg-zinc-800">
                        <div class="flex items-center gap-2">
                            <button @click.stop="expanded = !expanded" class="shrink-0 text-zinc-400 transition-colors">
                                <flux:icon.chevron-right class="size-4 transition-transform duration-200"
                                    ::class="expanded && 'rotate-90'" />
                            </button>
                            <button type="button" wire:click="selectFromOverview({{ $groupRow['group']->id }})"
                                class="hover:underline">
                                {{ $groupRow['group']->name }}
                            </button>
                            <span
                                class="text-xs font-normal text-zinc-500 dark:text-zinc-400">({{ count($groupRow['members']) }})</span>
                        </div>
                    </td>
                    @foreach ($this->days as $day)
                        @if (isset($groupRow['dates'][$day['date']]))
                            @php
                                $groupColorCounts = [];
                                foreach ($groupRow['members'] as $mr) {
                                    foreach ($mr['dates'][$day['date']] ?? [] as $p) {
                                        $c = $p['color'] ?? '_none';
                                        $groupColorCounts[$c] = ($groupColorCounts[$c] ?? 0) + 1;
                                    }
                                }
                                $groupTotal = array_sum($groupColorCounts);
                                $groupStops = [];
                                $groupPos = 0;
                                foreach ($groupColorCounts as $c => $count) {
                                    $pct = round(($count / $groupTotal) * 100, 2);
                                    $cssColor = $c === '_none' ? 'var(--color-zinc-300)' : "var(--color-{$c}-600)";
                                    $groupStops[] = "{$cssColor} {$groupPos}% " . ($groupPos + $pct) . '%';
                                    $groupPos += $pct;
                                }
                                $groupGradient =
                                    count($groupStops) === 1
                                        ? 'background-color: ' .
                                            (array_key_first($groupColorCounts) === '_none'
                                                ? 'var(--color-zinc-300)'
                                                : 'var(--color-' . array_key_first($groupColorCounts) . '-600)') .
                                            ';'
                                        : 'background: linear-gradient(to bottom, ' . implode(', ', $groupStops) . ');';
                            @endphp
                            <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0"
                                style="{{ $groupGradient }}">
                                <div class="aspect-square"></div>
                            </td>
                        @else
                            <td
                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                <div class="aspect-square"></div>
                            </td>
                        @endif
                    @endforeach
                </tr>

                @foreach ($groupRow['members'] as $memberRow)
                    <tr x-show="expanded" x-cloak
                        wire:key="overview-member-{{ $groupRow['group']->id }}-{{ $memberRow['user']->id }}">
                        <td
                            class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 pl-9 font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap min-w-[180px] bg-white dark:bg-zinc-900">
                            <button type="button"
                                wire:click="selectFromOverview({{ $groupRow['group']->id }}, {{ $memberRow['user']->id }})"
                                class="hover:underline">
                                {{ $memberRow['user']->name }}
                            </button>
                        </td>
                        @foreach ($this->days as $day)
                            @if (!empty($memberRow['dates'][$day['date']]))
                                @php
                                    $progs = $memberRow['dates'][$day['date']];
                                    $colorCounts = [];
                                    foreach ($progs as $p) {
                                        $c = $p['color'] ?? '_none';
                                        $colorCounts[$c] = ($colorCounts[$c] ?? 0) + 1;
                                    }
                                    $total = array_sum($colorCounts);
                                    $stops = [];
                                    $pos = 0;
                                    foreach ($colorCounts as $c => $count) {
                                        $pct = round(($count / $total) * 100, 2);
                                        $cssColor = $c === '_none' ? 'var(--color-zinc-300)' : "var(--color-{$c}-600)";
                                        $stops[] = "{$cssColor} {$pos}% " . ($pos + $pct) . '%';
                                        $pos += $pct;
                                    }
                                    $gradient =
                                        count($stops) === 1
                                            ? 'background-color: ' .
                                                (array_key_first($colorCounts) === '_none'
                                                    ? 'var(--color-zinc-300)'
                                                    : 'var(--color-' . array_key_first($colorCounts) . '-600)') .
                                                ';'
                                            : 'background: linear-gradient(to bottom, ' . implode(', ', $stops) . ');';
                                @endphp
                                <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0"
                                    style="{{ $gradient }}">
                                    <flux:dropdown position="bottom center">
                                        <button type="button" class="aspect-square w-full"></button>
                                        <flux:popover class="min-w-[10rem]">
                                            <div class="flex flex-col gap-1">
                                                @foreach ($memberRow['dates'][$day['date']] as $prog)
                                                    @if ($prog['color'])
                                                        <div class="flex flex-col text-[11px] px-1.5 py-0.5 rounded {{ \Coda\Cms\Support\ColorPalette::solidClasses($prog['color']) }}">
                                                            <span>{{ $prog['time'] }}</span>
                                                            <span class="truncate">{{ $prog['name'] }}</span>
                                                            @if ($prog['session'])
                                                                <span class="opacity-60">Session {{ $prog['session'] }}</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div class="flex flex-col text-[11px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                                            <span>{{ $prog['time'] }}</span>
                                                            <span class="truncate">{{ $prog['name'] }}</span>
                                                            @if ($prog['session'])
                                                                <span class="opacity-60">Session {{ $prog['session'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </flux:popover>
                                    </flux:dropdown>
                                </td>
                            @else
                                <td
                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                    <div class="aspect-square"></div>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>
</div>
