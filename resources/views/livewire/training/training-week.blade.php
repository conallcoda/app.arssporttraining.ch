<div class="space-y-4">
    @php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timePeriods = ['Morning', 'Afternoon', 'Evening'];
    @endphp

    {{-- Week Title --}}
    <div class="flex items-center justify-between">
        <flux:heading size="md">Week {{ $week->sequence + 1 }}</flux:heading>
        <div class="flex gap-2">
            <flux:button size="xs" variant="ghost" icon="pencil">Edit</flux:button>
            <flux:button size="xs" variant="ghost" icon="trash">Delete</flux:button>
        </div>
    </div>

    {{-- Week Grid --}}
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-left text-sm font-semibold w-24">
                        Time
                    </th>
                    @foreach ($days as $day)
                        <th class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-center text-sm font-semibold">
                            {{ $day }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($timePeriods as $period)
                    <tr>
                        <td class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-2 text-sm font-medium">
                            {{ $period }}
                        </td>
                        @foreach ($days as $day)
                            <td class="border border-zinc-200 dark:border-zinc-700 p-2 h-24 align-top hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors">
                                {{-- Session content will go here --}}
                                <div class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                                    <x-lucide-plus class="w-5 h-5" />
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
