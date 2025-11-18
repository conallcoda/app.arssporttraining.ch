<div class="space-y-4">
    @php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timePeriods = ['Morning', 'Afternoon'];

        $sessionsByDayAndSequence = [];
        foreach ($week->children as $session) {
            $day = $session->data->day;
            $slot = $session->data->slot;
            $sessionsByDayAndSequence[$day][$slot] = $session;
        }

        $categoriesById = $categories->keyBy('id');
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
                            @php
                                $session = $sessionsByDayAndSequence[$dayIndex][$sequenceIndex] ?? null;
                                $sessionCategory = $session && $session->data->category ? ($categoriesById[$session->data->category] ?? null) : null;
                            @endphp
                            <td
                                class="border border-zinc-200 dark:border-zinc-700 p-2 h-24 align-top hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors"
                                wire:click="openSessionModal('{{ $session?->uuid }}', {{ $dayIndex }}, {{ $sequenceIndex }})">

                                @if($sessionCategory)
                                    <div class="h-full flex items-center justify-center rounded px-2 py-1"
                                         style="background-color: {{ $this->getColorValue($sessionCategory->background_color) }}; color: {{ $this->getColorValue($sessionCategory->text_color) }};">
                                        <span class="text-sm font-medium">{{ $sessionCategory->name }}</span>
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

    <flux:modal name="session-modal" wire:model="showSessionModal" class="min-w-md">
        <form wire:submit="saveSession" class="space-y-6">
            @php
                $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $slotNames = ['Morning', 'Afternoon'];
            @endphp

            <div>
                <flux:heading size="lg">{{ $editingSessionUuid ? 'Edit Session' : 'Add Session' }}</flux:heading>
            </div>

            <flux:field>
                <flux:label>Day</flux:label>
                <flux:input value="{{ $dayNames[$sessionDay ?? 0] }}" disabled />
            </flux:field>

            <flux:field>
                <flux:label>Time Slot</flux:label>
                <flux:input value="{{ $slotNames[$sessionSlot ?? 0] }}" disabled />
            </flux:field>

            <flux:field>
                <flux:select wire:model="sessionCategory" label="Session Category" placeholder="Select a category">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="sessionCategory" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" wire:click="closeSessionModal">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Session</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
