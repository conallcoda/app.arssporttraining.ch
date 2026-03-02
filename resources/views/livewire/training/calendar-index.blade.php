<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item current>Calendar</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <div class="flex gap-6">
        <x-section title="Groups" class="w-64 shrink-0 sticky top-4 self-start max-h-[calc(100vh-6rem)] overflow-y-auto">
            <div class="flex flex-col gap-1">
                @foreach ($this->groups as $groupIndex => $group)
                    <div x-data="{ open: false }" wire:key="group-{{ $groupIndex }}">
                        <button
                            @click="open = !open"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                        >
                            <span>{{ $group['name'] }}</span>
                            <flux:icon.chevron-down
                                class="size-4 text-zinc-400 transition-transform duration-200"
                                ::class="open && 'rotate-180'"
                            />
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="flex flex-col gap-0.5 pl-4 pt-1">
                                @foreach ($group['athletes'] as $athlete)
                                    <div class="rounded px-3 py-1.5 text-sm text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 cursor-default">
                                        {{ $athlete }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-section>

        <div class="flex-1 min-w-0">
            <x-section title="Calendar" class="!p-0">
                <div class="px-4 pt-3 pb-2 flex items-center justify-between">
                    <flux:heading size="xl">{{ $this->title }}</flux:heading>
                    <flux:button variant="ghost" icon="calendar" size="sm"
                        wire:click="openSettings" />
                </div>
                <div class="overflow-x-auto">
                    <table class="border-collapse text-sm">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                <th rowspan="3" colspan="2" class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left min-w-[180px]">
                                </th>
                                @foreach ($this->months as $month)
                                    <th colspan="{{ $month['colspan'] }}" class="border border-zinc-300 dark:border-zinc-600 px-2 py-1.5 text-center text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800">
                                        {{ $month['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                @foreach ($this->weeks as $week)
                                    <th colspan="{{ $week['colspan'] }}" class="border border-zinc-300 dark:border-zinc-600 px-1 py-1 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400 {{ $week['week'] % 2 !== 0 ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '' }}">
                                        W{{ $week['week'] }}
                                    </th>
                                @endforeach
                            </tr>
                            <tr class="bg-zinc-100 dark:bg-zinc-800">
                                @foreach ($this->days as $day)
                                    <th class="border border-zinc-300 dark:border-zinc-600 px-1 py-2 text-center min-w-[40px] {{ $day['isToday'] ? 'bg-blue-100 dark:bg-blue-900/30' : ($day['oddWeek'] ? 'bg-zinc-100/50 dark:bg-zinc-700/20' : '') }}">
                                        <div class="text-[10px] leading-tight">{{ $day['label'] }}</div>
                                        <div class="font-medium text-xs">{{ $day['day'] }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->programs as $programIndex => $program)
                                <tr wire:key="program-{{ $programIndex }}-am">
                                    <td rowspan="2" class="sticky left-0 z-10 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap align-middle">
                                        {{ $program }}
                                    </td>
                                    <td class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                        AM
                                    </td>
                                    @foreach ($this->days as $day)
                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                            <div class="aspect-square"></div>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr wire:key="program-{{ $programIndex }}-pm">
                                    <td class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                        PM
                                    </td>
                                    @foreach ($this->days as $day)
                                        <td class="border border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                            <div class="aspect-square"></div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-section>
        </div>
    </div>

    <flux:modal name="calendar-settings" variant="flyout" class="max-w-md"
        x-on:close="$dispatch('calendar-settings-closed')">
        <div class="flex flex-col gap-6 p-2">
            <flux:heading size="lg">Calendar Settings</flux:heading>
            @foreach ($this->fieldsets as $item)
                <x-cms::form.fieldset
                    :fieldset="$item"
                    :prefix="$item->prefix ?? 'data'"
                    :showLegend="true"
                />
            @endforeach
            <flux:button variant="primary" wire:click="applySettings" class="w-full">
                Apply
            </flux:button>
        </div>
    </flux:modal>
</flux:main>
