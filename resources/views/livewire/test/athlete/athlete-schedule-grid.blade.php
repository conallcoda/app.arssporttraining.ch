<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @php
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $slotLabels = [0 => 'AM', 1 => 'PM'];
    @endphp

    @foreach ($this->schedule as $weekIndex => $week)
        @php
            $resolvedSlots = $this->getResolvedSlots($week);
        @endphp
        <div wire:key="week-{{ $week->id }}">
            <div class="px-3 py-2 bg-zinc-100 dark:bg-zinc-800 rounded-t-lg border border-b-0 border-zinc-300 dark:border-zinc-600">
                <span class="font-medium text-sm">Training Week {{ $weekIndex + 1 }}</span>
            </div>

            <div class="border border-zinc-300 dark:border-zinc-600 rounded-b-lg divide-y divide-zinc-200 dark:divide-zinc-700">
                @for ($dayIndex = 0; $dayIndex < 7; $dayIndex++)
                    @php
                        $amPrograms = $resolvedSlots[$dayIndex][0]['programs'] ?? [];
                        $pmPrograms = $resolvedSlots[$dayIndex][1]['programs'] ?? [];
                        $hasPrograms = !empty($amPrograms) || !empty($pmPrograms);
                    @endphp

                    @if ($hasPrograms)
                        <div class="flex items-start gap-3 px-3 py-2">
                            <div class="w-10 shrink-0 pt-0.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                {{ $dayNames[$dayIndex] }}
                            </div>
                            <div class="flex-1 space-y-1">
                                @foreach ([0, 1] as $slotKey)
                                    @php
                                        $slotPrograms = $resolvedSlots[$dayIndex][$slotKey]['programs'] ?? [];
                                    @endphp
                                    @if (!empty($slotPrograms))
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 shrink-0 text-[10px] text-zinc-400 dark:text-zinc-500">{{ $slotLabels[$slotKey] }}</span>
                                            <div class="flex-1 flex flex-col gap-1">
                                                @foreach ($slotPrograms as $programId)
                                                    @php
                                                        $exercises = $this->getProgramExercises($programId);
                                                    @endphp
                                                    <div x-data="{ open: false }">
                                                        <button x-on:click="open = !open" type="button"
                                                            class="w-full rounded px-2 py-0.5 text-xs font-medium flex items-center justify-between"
                                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($this->getProgramColor($programId)) }}">
                                                            <span class="truncate">{{ $this->getProgramName($programId) }}</span>
                                                            <svg class="size-3 shrink-0 transition-transform duration-200" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                        <div x-show="open" x-collapse>
                                                            @if (!empty($exercises))
                                                                <table class="w-full text-xs mt-1">
                                                                    @foreach ($exercises as $exercise)
                                                                        <tr>
                                                                            <td class="py-0.5 px-2 text-zinc-600 dark:text-zinc-300">{{ $exercise['name'] }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </table>
                                                            @else
                                                                <p class="text-xs text-zinc-400 dark:text-zinc-500 px-2 py-1">No exercises.</p>
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
                    @endif
                @endfor
            </div>
        </div>
    @endforeach
</div>
