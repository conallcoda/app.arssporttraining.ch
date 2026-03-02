<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item current>Calendar</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <div class="flex gap-6">
        <livewire:user-group-sidebar mode="single-athlete" :initial-group="$group !== '' ? (int) $group : null" :initial-user="$user !== '' ? (int) $user : null" />

        <div class="flex-1 min-w-0">
            <x-section title="Calendar" class="!p-0">
                <div class="px-4 pt-3 pb-2 flex items-center justify-between">
                    <flux:heading size="xl">
                        @if ($this->selectionName)
                            {{ $this->selectionName }}, {{ $this->title }}
                        @else
                            {{ $this->title }}
                        @endif
                    </flux:heading>
                    <flux:button variant="ghost" icon="calendar" size="sm" wire:click="openSettings" />
                </div>

                @if ($this->hasSelection())
                    <div class="overflow-x-auto">
                        <table class="border-collapse text-sm">
                            <thead>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                    <th rowspan="3" colspan="2"
                                        class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left min-w-[180px]">
                                    </th>
                                    @foreach ($this->months as $month)
                                        <th colspan="{{ $month['colspan'] }}"
                                            class="border border-zinc-300 dark:border-zinc-600 px-2 py-1.5 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800">
                                            {{ $month['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                    @foreach ($this->weeks as $week)
                                        <th colspan="{{ $week['colspan'] }}"
                                            class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 {{ $week['week'] % 2 !== 0 ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '' }}">
                                            W{{ $week['week'] }}
                                        </th>
                                    @endforeach
                                </tr>
                                <tr class="bg-zinc-100 dark:bg-zinc-800">
                                    @foreach ($this->days as $day)
                                        <th
                                            class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 text-center min-w-[40px] {{ $day['isToday'] ? 'bg-blue-100 dark:bg-blue-900/30' : ($day['oddWeek'] ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '') }}">
                                            <div class="text-[10px] leading-tight">{{ $day['label'] }}</div>
                                            <div class="font-medium text-xs">{{ $day['day'] }}</div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->programs as $programIndex => $program)
                                    <tr wire:key="program-{{ $programIndex }}-am">
                                        <td rowspan="2"
                                            class="sticky left-0 z-10 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap align-middle">
                                            {{ $program }}
                                        </td>
                                        <td
                                            class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                            AM
                                        </td>
                                        @foreach ($this->days as $day)
                                            <td
                                                class="border border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                                <div class="aspect-square"></div>
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr wire:key="program-{{ $programIndex }}-pm">
                                        <td
                                            class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                            PM
                                        </td>
                                        @foreach ($this->days as $day)
                                            <td
                                                class="border border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                                <div class="aspect-square"></div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No selection
                        </flux:heading>
                        <flux:text class="text-zinc-400 dark:text-zinc-500 mt-1">Please select an athlete or group to
                            view their schedule.</flux:text>
                    </div>
                @endif
            </x-section>
        </div>
    </div>

    <flux:modal name="calendar-settings" variant="flyout" class="max-w-md"
        x-on:close="$dispatch('calendar-settings-closed')">
        <div class="flex flex-col gap-6 p-2">
            <flux:heading size="lg">Calendar Settings</flux:heading>
            @foreach ($this->fieldsets as $item)
                <x-cms::form.fieldset :fieldset="$item" :prefix="$item->prefix ?? 'data'" :showLegend="true" />
            @endforeach
            <flux:button variant="primary" wire:click="applySettings" class="w-full">
                Apply
            </flux:button>
        </div>
    </flux:modal>
</flux:main>
