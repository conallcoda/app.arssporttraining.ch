<div>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create Season (Itinerary)</h1>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-3">
                <livewire:planner.itinerary-config />
            </div>

            <div class="col-span-9">
                <flux:card class="h-full">
                    <flux:heading size="lg">Weekly Itinerary</flux:heading>

                    @if ($this->firstBlock)
                        <flux:tabs variant="segmented" class="mt-4 mb-4">
                            @foreach ($this->tree->root->children as $blockIndex => $block)
                                <flux:tab>Block {{ $blockIndex + 1 }}</flux:tab>
                            @endforeach
                        </flux:tabs>
                        <div class="overflow-x-auto">
                            <table class="border-collapse border border-zinc-300 dark:border-zinc-600 text-sm w-full">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800">
                                        <th class="border border-zinc-300 dark:border-zinc-600 px-3 py-2"
                                            colspan="2"></th>
                                        @foreach ($this->days as $day)
                                            <th
                                                class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 min-w-[80px]">
                                                {{ $day }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->firstBlock->children as $weekIndex => $week)
                                        @foreach ($this->slots as $slotIndex => $slotName)
                                            <tr>
                                                @if ($slotIndex === 0)
                                                    <td class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium bg-zinc-50 dark:bg-zinc-800/50"
                                                        rowspan="2">
                                                        Week {{ $weekIndex + 1 }}
                                                    </td>
                                                @endif
                                                <td
                                                    class="border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50">
                                                    {{ $slotName }}
                                                </td>
                                                @for ($day = 0; $day < 7; $day++)
                                                    @php
                                                        $categoryId = $this->getItinerary(
                                                            $week->uuid,
                                                            $slotIndex,
                                                            $day,
                                                        );
                                                        $category = $categoryId
                                                            ? $this->categoriesById[$categoryId] ?? null
                                                            : null;
                                                        $sessionName = $this->getSessionName(
                                                            $week->uuid,
                                                            $slotIndex,
                                                            $day,
                                                        );
                                                    @endphp
                                                    <td class="border border-zinc-300 dark:border-zinc-600 p-1 h-12 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                                        wire:click="openSessionModal('{{ $week->uuid }}', {{ $slotIndex }}, {{ $day }})">
                                                        @if ($category)
                                                            <div class="h-full flex items-center justify-center rounded px-2 py-1"
                                                                style="background-color: {{ $this->getColorValue($category->background_color) }}; color: {{ $this->getColorValue($category->text_color) }};">
                                                                <span
                                                                    class="text-xs font-medium">{{ $sessionName ?? $category->name }}</span>
                                                            </div>
                                                        @else
                                                            <div
                                                                class="h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                                                                <x-lucide-plus class="w-4 h-4" />
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
                    @endif
                </flux:card>
            </div>
        </div>
    </div>

    <flux:modal name="session-modal" wire:model="showSessionModal" variant="flyout" class="w-96">
        <form wire:submit="saveSession" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingSessionUuid ? 'Edit Session' : 'Add Session' }}
                </flux:heading>
                <flux:subheading>
                    {{ $this->daysFull[$editingDay ?? 0] }} {{ $this->slots[$editingSlot ?? 0] }}
                </flux:subheading>
            </div>

            <flux:input wire:model="sessionName" label="Session Name" placeholder="Optional custom name" />

            <flux:field>
                <flux:label>Session Category</flux:label>
                <flux:select wire:model="sessionCategory" placeholder="Select a category">
                    @foreach ($this->categories as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="sessionCategory" />
            </flux:field>

            <flux:field>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:label>Exercises</flux:label>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="addExercise"
                            icon="plus">
                            Add
                        </flux:button>
                    </div>

                    @if (count($sessionExercises) > 0)
                        <div class="space-y-2">
                            @foreach ($sessionExercises as $index => $exerciseId)
                                @php
                                    $isFirst = $index === 0;
                                    $isLast = $index === count($sessionExercises) - 1;
                                @endphp
                                <div class="flex items-center gap-2" wire:key="exercise-{{ $index }}">
                                    <div class="flex-1">
                                        <flux:select wire:model="sessionExercises.{{ $index }}"
                                            placeholder="Select an exercise" size="sm">
                                            <flux:select.option value="">Select an exercise</flux:select.option>
                                            @foreach ($this->exercises as $exercise)
                                                <flux:select.option value="{{ $exercise->id }}">{{ $exercise->name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="flex gap-0.5">
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="moveExerciseUp({{ $index }})" :disabled="$isFirst">
                                            <x-lucide-chevron-up class="w-4 h-4" />
                                        </flux:button>
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="moveExerciseDown({{ $index }})" :disabled="$isLast">
                                            <x-lucide-chevron-down class="w-4 h-4" />
                                        </flux:button>
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="removeExercise({{ $index }})">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-zinc-500">No exercises added yet.</p>
                    @endif
                </div>
            </flux:field>

            <div class="flex gap-2 justify-between pt-4">
                <div>
                    @if ($editingSessionUuid)
                        <flux:button type="button" variant="danger" size="sm" wire:click="deleteSession"
                            wire:confirm="Are you sure you want to delete this session?">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeSessionModal">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
