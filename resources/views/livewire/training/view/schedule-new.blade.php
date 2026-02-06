<div class="flex gap-6">
    <x-section title="Schedules" class="w-64 shrink-0 sticky top-4 self-start">
        <div class="flex flex-col gap-1">
            <flux:button wire:click="selectUser(null)" variant="{{ $user === null ? 'primary' : 'ghost' }}"
                class="justify-start">
                <span class="flex-1 text-left">Default</span>
            </flux:button>

            <div class="mx-3">
                <flux:separator class="my-2" variant="subtle" />
            </div>

            @foreach ($this->users as $userItem)
                @php
                    $hasCustomSchedule = $this->hasUserSchedule($userItem->id);
                    $changeCount = $hasCustomSchedule ? $this->countUserScheduleChanges($userItem->id) : 0;
                    $isSelected = $user === $userItem->id;
                @endphp
                <flux:button wire:key="user-btn-{{ $userItem->id }}" wire:click="selectUser({{ $userItem->id }})"
                    variant="{{ $isSelected ? 'primary' : 'ghost' }}" class="justify-start">
                    <span class="flex-1 text-left">{{ $userItem->name }}</span>
                    @if ($hasCustomSchedule)
                        <flux:badge size="sm"
                            class="{{ $isSelected ? 'bg-white/20 !text-white dark:!text-black' : '' }}">
                            {{ $changeCount }}</flux:badge>
                    @endif
                </flux:button>
            @endforeach
        </div>
    </x-section>

    <div class="flex-1 space-y-6">
        @if ($user === null)
            <flux:heading size="xl">Default Schedule</flux:heading>
        @elseif ($this->selectedUser)
            <flux:heading size="xl">{{ $this->selectedUser->name }}</flux:heading>
        @endif

        <x-section title="Weekly Schedule" class="!p-0">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm table-fixed">
                    <colgroup>
                        <col style="width: 8%" />
                        <col style="width: 4%" />
                        <col class="w-auto" />
                        <col class="w-auto" />
                        <col class="w-auto" />
                        <col class="w-auto" />
                        <col class="w-auto" />
                        <col class="w-auto" />
                        <col class="w-auto" />
                    </colgroup>
                    <thead>
                        <tr class="bg-zinc-100 dark:bg-zinc-800">
                            <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                            <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2"></th>
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                                <th class="border-b border-zinc-300 dark:border-zinc-600 px-3 py-2">{{ $day }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->schedule as $weekIndex => $week)
                            @php
                                $isLinked = $week['linkedTo'] !== null;
                                $resolvedSlots = $this->getResolvedSlots($week);
                                $linkedToIndex = $isLinked
                                    ? collect($this->schedule)->search(fn($w) => $w['id'] === $week['linkedTo'])
                                    : null;
                            @endphp
                            @foreach ([0 => 'AM', 1 => 'PM'] as $slotKey => $slotLabel)
                                <tr wire:key="week-{{ $week['id'] }}-{{ $slotKey }}-{{ $user ?? 'default' }}"
                                    class="{{ $isLinked ? 'bg-zinc-50 dark:bg-zinc-900/50' : '' }}">
                                    @if ($slotKey === 0)
                                        <td rowspan="2"
                                            class="border border-l-0 {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} px-3 py-2 font-medium bg-zinc-50 dark:bg-zinc-800/50 text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <span>Week {{ $weekIndex + 1 }}</span>
                                                @if ($isLinked)
                                                    <div class="flex items-center gap-1 text-xs text-zinc-500">
                                                        <flux:icon.lock class="size-3" />
                                                        <span>W{{ $linkedToIndex + 1 }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                    <td
                                        class="border {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} px-2 py-2 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 text-center text-xs">
                                        {{ $slotLabel }}
                                    </td>
                                    @for ($dayIndex = 0; $dayIndex < 7; $dayIndex++)
                                        @php
                                            $slotData = $resolvedSlots[$dayIndex][$slotKey] ?? [];
                                            $programId = $slotData['programId'] ?? null;
                                            $programName = $programId ? $this->programOptions[$programId] ?? '?' : null;
                                            $programColor = $this->getProgramColor($programId);
                                        @endphp
                                        <td class="border {{ $isLinked ? 'border-dashed border-zinc-400 dark:border-zinc-500' : 'border-zinc-300 dark:border-zinc-600' }} p-1 h-12">
                                            @if ($programId)
                                                <div class="h-full flex items-center justify-center rounded px-2 py-1 text-xs font-medium bg-{{ $programColor ?? 'blue' }}-500 text-white">
                                                    <span class="truncate">{{ $programName }}</span>
                                                </div>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-section>
    </div>
</div>
