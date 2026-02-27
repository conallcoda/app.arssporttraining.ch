<div class="grid grid-cols-1 gap-4 lg:grid-cols-2 text-sm">
    @php
        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $slotLabels = [0 => 'AM', 1 => 'PM'];
    @endphp

    @foreach ($this->schedule as $weekIndex => $week)
        @php
            $resolvedSlots = $this->getResolvedSlots($week);
        @endphp
        <div wire:key="week-{{ $week->id }}" class="rounded-lg border border-zinc-300 dark:border-zinc-600 overflow-hidden">
            <div class="bg-zinc-100 dark:bg-zinc-800 px-3 py-2 font-medium border-b border-zinc-300 dark:border-zinc-600">
                Training Week {{ $weekIndex + 1 }}
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @for ($dayIndex = 0; $dayIndex < 7; $dayIndex++)
                    @php
                        $amPrograms = $resolvedSlots[$dayIndex][0]['programs'] ?? [];
                        $pmPrograms = $resolvedSlots[$dayIndex][1]['programs'] ?? [];
                        $hasPrograms = !empty($amPrograms) || !empty($pmPrograms);
                    @endphp

                    @if ($hasPrograms)
                        <div x-data="{ dayOpen: false }" wire:key="week-{{ $week->id }}-day-{{ $dayIndex }}">
                            <button
                                x-on:click="dayOpen = !dayOpen"
                                type="button"
                                class="w-full px-3 py-2 font-medium flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                            >
                                <span>{{ $dayNames[$dayIndex] }}</span>
                                <flux:icon.chevron-down class="size-4 shrink-0 text-zinc-400 transition-transform duration-200" ::class="dayOpen && 'rotate-180'" />
                            </button>

                            <div x-show="dayOpen" x-collapse>
                                <div class="px-3 pb-3 space-y-3">
                                    @foreach ([0, 1] as $slotKey)
                                        @php
                                            $slotPrograms = $resolvedSlots[$dayIndex][$slotKey]['programs'] ?? [];
                                        @endphp
                                        @if (!empty($slotPrograms))
                                            <div>
                                                <div class="py-1 font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $slotLabels[$slotKey] }}</div>

                                                <div class="space-y-1">
                                                    @foreach ($slotPrograms as $programId)
                                                        @php
                                                            $exercises = $this->getProgramExercises($programId);
                                                        @endphp
                                                        <div x-data="{ open: false }" wire:key="week-{{ $week->id }}-day-{{ $dayIndex }}-slot-{{ $slotKey }}-program-{{ $programId }}">
                                                            <button
                                                                x-on:click="open = !open"
                                                                type="button"
                                                                class="w-full rounded px-3 py-2 font-medium flex items-center justify-between"
                                                                style="{{ \Coda\Cms\Support\ColorPalette::solid($this->getProgramColor($programId)) }}"
                                                            >
                                                                <span class="truncate">{{ $this->getProgramName($programId) }}</span>
                                                                <flux:icon.chevron-down class="size-4 shrink-0 transition-transform duration-200" ::class="open && 'rotate-180'" />
                                                            </button>

                                                            <div x-show="open" x-collapse>
                                                                @if (!empty($exercises))
                                                                    <ul class="py-1 space-y-0.5">
                                                                        @foreach ($exercises as $exercise)
                                                                            <li wire:key="exercise-{{ $exercise['id'] }}" class="px-3 py-1">
                                                                                <button
                                                                                    wire:click="openExercise({{ $exercise['id'] }}, {{ $programId }}, '{{ $week->id }}', {{ $dayIndex }}, {{ $slotKey }})"
                                                                                    type="button"
                                                                                    class="text-left text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white hover:underline"
                                                                                >
                                                                                    {{ $exercise['name'] }}
                                                                                </button>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @else
                                                                    <p class="px-3 py-2 text-zinc-400 dark:text-zinc-500">No exercises.</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endfor
            </div>
        </div>
    @endforeach

    <x-athlete.exercise-detail-modal />
</div>
