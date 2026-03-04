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
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl">
                            @if ($this->selectionName)
                                {{ $this->selectionName }}, {{ $this->title }}
                            @else
                                {{ $this->title }}
                            @endif
                        </flux:heading>
                        <flux:button variant="ghost" icon="pencil" size="sm" wire:click="openCalendarRange" />
                    </div>
                    @if ($this->hasSelection())
                        <div class="flex items-center gap-2">
                            <flux:radio.group wire:model.live="viewMode" variant="segmented" size="sm">
                                <flux:radio value="program" label="Program Grid" />
                                <flux:radio value="week" label="Week Grid" />
                            </flux:radio.group>
                        </div>
                    @endif
                </div>

                @if ($this->hasSelection() && $this->programs->isNotEmpty())
                    @if ($viewMode === 'program')
                        <div class="px-4 py-2 flex justify-end">
                            <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddContent">Add Program</flux:button>
                        </div>
                    @endif

                    @if ($viewMode === 'week')
                        @include('livewire.training.partials.calendar-week-grid')
                    @else
                        @include('livewire.training.partials.calendar-program-grid')
                    @endif
                @elseif ($this->hasSelection())
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <flux:icon.calendar class="size-10 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <flux:heading size="lg" class="text-zinc-500 dark:text-zinc-400">No programs assigned
                        </flux:heading>
                        <flux:button variant="primary" icon="plus" size="sm" wire:click="openAddContent" class="mt-3">Add Program</flux:button>
                    </div>
                @else
                    @include('livewire.training.partials.calendar-overview-grid')
                @endif
            </x-section>
        </div>
    </div>

    <livewire:training.calendar-range-form />

    <livewire:training.week-slot-form />

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
                                    @if ($option->exerciseCategory?->color)
                                        <span class="w-2 h-2 rounded-full shrink-0"
                                            style="{{ \Coda\Cms\Support\ColorPalette::solid($option->exerciseCategory->color) }}"></span>
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
