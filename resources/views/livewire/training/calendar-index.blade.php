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
                        <flux:button variant="ghost" icon="calendar" size="sm" wire:click="openSettings" />
                    </div>
                </div>

                @if ($this->hasSelection() && $this->programs->isNotEmpty())
                    <div class="overflow-x-auto" style="--program-col-width: 180px">
                        <table class="border-separate border-spacing-0 text-sm border-t border-l border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                                    <th rowspan="3"
                                        class="sticky left-0 z-10 bg-zinc-100 dark:bg-zinc-800 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 text-left w-[var(--program-col-width)] min-w-[var(--program-col-width)] max-w-[var(--program-col-width)]">
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
                            <tbody>
                                @foreach ($this->programs as $entry)
                                    <tr wire:key="program-{{ $entry->id }}-am">
                                        <td rowspan="2"
                                            class="sticky left-0 z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-3 py-2 font-medium text-zinc-700 dark:text-zinc-300 whitespace-nowrap align-middle w-[var(--program-col-width)] min-w-[var(--program-col-width)] max-w-[var(--program-col-width)]">
                                            <div class="flex items-center gap-2">
                                                @if ($entry->program->programCategory?->color)
                                                    <span class="w-1.5 self-stretch rounded-full shrink-0"
                                                        style="{{ \Coda\Cms\Support\ColorPalette::solid($entry->program->programCategory->color) }}"></span>
                                                @endif
                                                <div class="flex flex-col gap-0.5">
                                                    <button type="button" wire:click="openEditProgram({{ $entry->id }})"
                                                        class="text-left hover:underline">
                                                        {{ $entry->program->name }}
                                                    </button>
                                                    @if ($entry->sourcePlan)
                                                        <flux:badge size="sm" variant="outline">{{ $entry->sourcePlan->name }}</flux:badge>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                            AM
                                        </td>
                                        @foreach ($this->days as $day)
                                            <td
                                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                                <div class="aspect-square"></div>
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr wire:key="program-{{ $entry->id }}-pm">
                                        <td
                                            class="sticky left-[var(--program-col-width)] z-10 bg-white dark:bg-zinc-900 border-r border-b border-zinc-300 dark:border-zinc-600 px-1.5 py-1 text-[10px] text-zinc-400 dark:text-zinc-500">
                                            PM
                                        </td>
                                        @foreach ($this->days as $day)
                                            <td
                                                class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0 {{ $day['isToday'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($day['oddWeek'] ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '') }}">
                                                <div class="aspect-square"></div>
                                            </td>
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

    <livewire:cms.form-modal
        name="edit-program"
        title="Edit Exercise Program"
        :formDataClass="\App\Data\Training\ExerciseProgramData::class"
        :flyout="false"
        maxWidth="max-w-lg"
        :showDelete="true"
    />

    <flux:modal name="confirm-delete-program" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Remove program?</flux:heading>
                <flux:text class="mt-2">
                    You're about to remove this program from the calendar.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteEditingTrainingProgram">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

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
                        <flux:input wire:model.live.debounce.300ms="addContentSearch" placeholder="Search plans..." size="sm" icon="magnifying-glass" />
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
                        <flux:input wire:model.live.debounce.300ms="addContentSearch" placeholder="Search programs..." size="sm" icon="magnifying-glass" />
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
                        <flux:input wire:model.live.debounce.300ms="addContentSearch" placeholder="Search exercises..." size="sm" icon="magnifying-glass" />
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
