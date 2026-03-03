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
                    <div class="flex items-center gap-1">
                        @if ($this->hasSelection())
                            <flux:button variant="ghost" icon="plus" size="sm" wire:click="openAddContent" />
                        @endif
                        <flux:button variant="ghost" icon="calendar" size="sm" wire:click="openCalendarRange" />
                    </div>
                </div>

                @if ($this->hasSelection() && $this->programs->isNotEmpty())
                    <div class="overflow-x-auto" style="--program-col-width: 180px"
                        x-data="{
                            updateColWidth() {
                                const th = $el.querySelector('thead th');
                                if (th) $el.style.setProperty('--program-col-width', th.offsetWidth + 'px');
                            }
                        }"
                        x-init="$nextTick(() => updateColWidth())"
                        x-on:resize.window="updateColWidth()"
                    >
                        <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                    <th rowspan="3"
                                        class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left min-w-[180px]">
                                    </th>
                                    <th rowspan="3"
                                        class="sticky left-[var(--program-col-width)] z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-2">
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
                            <tbody x-data="sortable_programs">
                                @foreach ($this->programs as $entry)
                                    <tr wire:key="program-{{ $entry->id }}-am">
                                        @php
                                            $isInherited = $this->user !== '' && $entry->isGroupLevel();
                                        @endphp
                                        <td rowspan="2" x-data="{ open: false }"
                                            data-program-id="{{ $entry->id }}"
                                            @dragover="handleDragOver($event, {{ $entry->id }})"
                                            @dragleave="handleDragLeave($event)"
                                            @drop="handleDrop($event, {{ $entry->id }})"
                                            class="sticky left-0 z-10 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap align-top min-w-[180px] {{ $entry->program->programCategory?->color ? \Coda\Cms\Support\ColorPalette::lightOpaque($entry->program->programCategory->color) : 'bg-white dark:bg-zinc-900' }}">
                                            <div class="flex items-center gap-2">
                                                @if ($isInherited)
                                                    <div class="shrink-0 text-zinc-300 dark:text-zinc-600">
                                                        <flux:icon.users class="size-4" />
                                                    </div>
                                                @else
                                                    <div draggable="true"
                                                        @dragstart="handleDragStart($event, {{ $entry->id }})"
                                                        @dragend="handleDragEnd($event)"
                                                        class="shrink-0 cursor-grab active:cursor-grabbing text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                                        <flux:icon.grip class="size-4" />
                                                    </div>
                                                @endif
                                                <div class="flex flex-col gap-0.5 flex-1 min-w-0">
                                                    @if ($isInherited)
                                                        <span class="text-zinc-500 dark:text-zinc-400">{{ $entry->program->name }}</span>
                                                        <flux:badge size="sm" variant="outline" class="w-fit">Group</flux:badge>
                                                    @else
                                                        <button type="button" wire:click="openEditProgram({{ $entry->id }})"
                                                            class="text-left hover:underline">
                                                            {{ $entry->program->name }}
                                                        </button>
                                                        @if ($entry->sourcePlan)
                                                            <flux:badge size="sm" variant="outline">{{ $entry->sourcePlan->name }}</flux:badge>
                                                        @endif
                                                    @endif
                                                </div>
                                                @if ($entry->program->exercises->isNotEmpty())
                                                    <button
                                                        @click="open = !open"
                                                        class="shrink-0 ml-3 p-1.5 text-zinc-400 transition-colors"
                                                    >
                                                        <flux:icon.chevron-down
                                                            class="size-4 transition-transform duration-200"
                                                            ::class="open && 'rotate-180'"
                                                        />
                                                    </button>
                                                @endif
                                            </div>
                                            @if ($entry->program->exercises->isNotEmpty())
                                                <div x-show="open" x-collapse class="mt-1">
                                                    <div class="flex flex-col gap-0.5 pl-4">
                                                        @foreach ($entry->program->exercises as $exercise)
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 font-normal">
                                                                {{ $exercise->name }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td
                                            class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                            AM
                                        </td>
                                        @foreach ($this->days as $day)
                                            @php
                                                $amKey = $entry->id . '-' . $day['date'] . '-0';
                                                $amActive = $this->slotMap[$amKey] ?? false;
                                                $amState = $this->slotState[$amKey] ?? 'direct';
                                            @endphp
                                            @if ($amActive && $amState === 'inherited')
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 0)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-emerald-400/30 dark:bg-emerald-500/20 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @elseif ($amActive && $amState === 'overridden')
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 0)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-sky-400/60 dark:bg-sky-500/40 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @elseif ($amActive)
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 0)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-emerald-400/60 dark:bg-emerald-500/40 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @elseif (! $amActive && $amState === 'overridden')
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 0)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-red-200/30 dark:bg-red-900/10 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @else
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 0)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                    <tr wire:key="program-{{ $entry->id }}-pm">
                                        <td
                                            class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                            PM
                                        </td>
                                        @foreach ($this->days as $day)
                                            @php
                                                $pmKey = $entry->id . '-' . $day['date'] . '-1';
                                                $pmActive = $this->slotMap[$pmKey] ?? false;
                                                $pmState = $this->slotState[$pmKey] ?? 'direct';
                                            @endphp
                                            @if ($pmActive && $pmState === 'inherited')
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 1)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-emerald-400/30 dark:bg-emerald-500/20 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @elseif ($pmActive && $pmState === 'overridden')
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 1)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-sky-400/60 dark:bg-sky-500/40 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @elseif ($pmActive)
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 1)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-emerald-400/60 dark:bg-emerald-500/40 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @elseif (! $pmActive && $pmState === 'overridden')
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 1)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 bg-red-200/30 dark:bg-red-900/10 cursor-pointer">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @else
                                                <td wire:click="toggleSlot({{ $entry->id }}, '{{ $day['date'] }}', 1)"
                                                    class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 cursor-pointer {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                                    <div class="aspect-square"></div>
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($this->hasSelection())
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No programs assigned
                        </flux:heading>
                        <flux:text class="text-zinc-400 dark:text-zinc-500 mt-1">Use the <strong>+</strong> button to add
                            content.</flux:text>
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

    <livewire:training.calendar-range-form />

    <livewire:cms.form-modal
        name="edit-program"
        title="Edit Exercise Program"
        :formDataClass="\App\Data\Training\ExerciseProgramData::class"
        :flyout="true"
        maxWidth="max-w-lg"
        :showDelete="true"
    />

    <x-cms::confirm-modal
        name="confirm-delete-program"
        heading="Remove program?"
        description="You're about to remove this program from the calendar. This action cannot be reversed."
        confirmLabel="Delete"
        action="deleteEditingTrainingProgram"
    />

    <flux:modal name="add-content" variant="flyout" class="max-w-md">
        <div class="flex flex-col gap-4 p-2">
            <flux:heading size="lg">Add Content</flux:heading>

            <flux:tab.group>
                <flux:tabs wire:model.live="addContentTab">
                    <flux:tab name="plan">Plan</flux:tab>
                    <flux:tab name="program">Program</flux:tab>
                    <flux:tab name="exercise">Exercise</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="plan" class="!px-0">
                    <div class="flex flex-col gap-3">
                        <x-cms::form.field :field="\Coda\Cms\Form\Fields\Search::make('addContentSearch')" />
                        <div class="flex flex-col gap-1 max-h-80 overflow-y-auto">
                            @foreach ($this->addContentOptions as $option)
                                <button type="button" wire:click="addFromPlan({{ $option->id }})"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-left rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.clipboard-list class="size-4 text-zinc-400" />
                                    {{ $option->name }}
                                </button>
                            @endforeach
                            @if ($this->addContentOptions->isEmpty())
                                <flux:text class="px-3 py-4 text-center text-zinc-400">No plans found.</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="program" class="!px-0">
                    <div class="flex flex-col gap-3">
                        <x-cms::form.field :field="\Coda\Cms\Form\Fields\Search::make('addContentSearch')" />
                        <div class="flex flex-col gap-1 max-h-80 overflow-y-auto">
                            @foreach ($this->addContentOptions as $option)
                                <button type="button" wire:click="addFromProgram({{ $option->id }})"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-left rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    @if ($option->programCategory?->color)
                                        <span class="w-2 h-2 rounded-full shrink-0"
                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($option->programCategory->color) }}"></span>
                                    @endif
                                    {{ $option->name }}
                                </button>
                            @endforeach
                            @if ($this->addContentOptions->isEmpty())
                                <flux:text class="px-3 py-4 text-center text-zinc-400">No programs found.</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="exercise" class="!px-0">
                    <div class="flex flex-col gap-3">
                        <flux:field>
                            <flux:label>Category</flux:label>
                            <flux:select wire:model="addExerciseCategoryId" placeholder="Select a category...">
                                @foreach ($this->categoryOptions as $id => $name)
                                    <flux:select.option :value="$id">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('addExerciseCategoryId')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                        <x-cms::form.field :field="\Coda\Cms\Form\Fields\Search::make('addContentSearch')" />
                        <div class="flex flex-col gap-1 max-h-80 overflow-y-auto">
                            @foreach ($this->addContentOptions as $option)
                                <button type="button" wire:click="addFromExercise({{ $option->id }})"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-left rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    <flux:icon.dumbbell class="size-4 text-zinc-400" />
                                    {{ $option->name }}
                                </button>
                            @endforeach
                            @if ($this->addContentOptions->isEmpty())
                                <flux:text class="px-3 py-4 text-center text-zinc-400">No exercises found.</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
    </flux:modal>
</flux:main>
