<div class="space-y-4">
    @php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timePeriods = ['Morning', 'Afternoon'];

        $sessionsByDayAndSequence = [];
        foreach ($week->children as $session) {
            $day = $session->period->day;
            $sequence = $session->period->sequence;
            $sessionsByDayAndSequence[$day][$sequence] = $session;
        }
    @endphp

    {{-- Week Title --}}
    <div class="flex items-center justify-between">
        <flux:heading size="md">Week {{ $week->sequence + 1 }}</flux:heading>
    </div>

    {{-- Week Grid --}}
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th
                        class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-left text-sm font-semibold w-24">
                        Time
                    </th>
                    @foreach ($days as $day)
                        <th
                            class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-center text-sm font-semibold">
                            {{ $day }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($timePeriods as $sequenceIndex => $period)
                    <tr>
                        <td
                            class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-sm font-medium">
                            {{ $period }}
                        </td>
                        @foreach ($days as $dayIndex => $day)
                            <td
                                class="border border-zinc-200 dark:border-zinc-700 p-2 h-24 align-top hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors">
                                @php
                                    $session = $sessionsByDayAndSequence[$dayIndex][$sequenceIndex] ?? null;
                                @endphp

                                @if($session && $session->category)
                                    <div class="h-full flex items-center justify-center rounded px-2 py-1"
                                         style="background-color: {{ $this->getColorValue($session->category->background_color) }}; color: {{ $this->getColorValue($session->category->text_color) }};">
                                        <span class="text-sm font-medium">{{ $session->category->name }}</span>
                                    </div>
                                @else
                                    <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                                        <x-lucide-plus class="w-5 h-5" />
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
