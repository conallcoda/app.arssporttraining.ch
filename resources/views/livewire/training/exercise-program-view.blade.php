<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/programs">Programs</flux:navbar.item>
        <span class="text-zinc-400 dark:text-zinc-500">/</span>
        <flux:navbar.item current>{{ $exerciseProgram->name }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <div x-data="{
        editing: false,
        name: @js($exerciseProgram->name),
        originalName: @js($exerciseProgram->name),
        startEditing() {
            this.editing = true;
            this.$nextTick(() => this.$refs.input.select());
        },
        save() {
            this.editing = false;
            if (this.name.trim() && this.name !== this.originalName) {
                this.originalName = this.name;
                $wire.updateName(this.name);
            } else {
                this.name = this.originalName;
            }
        },
        cancel() {
            this.name = this.originalName;
            this.editing = false;
        }
    }" class="mb-6 group">
        <div x-show="!editing" @click="startEditing()" class="flex items-center gap-2 cursor-pointer">
            <flux:heading size="xl" level="1" class="hover:text-zinc-600 dark:hover:text-zinc-300">
                <span x-text="name"></span>
            </flux:heading>
            <flux:icon.pencil class="w-5 h-5 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" />
        </div>
        <input x-show="editing" x-cloak x-ref="input" x-model="name" @keydown.enter.prevent="save()"
            @keydown.escape.prevent="cancel()" @blur="save()" type="text"
            class="text-2xl font-semibold bg-transparent border-b-2 border-zinc-300 dark:border-zinc-600 focus:border-blue-500 focus:outline-none w-full max-w-lg text-zinc-800 dark:text-white" />
    </div>

    <div class="space-y-6">
        <div class="flex gap-6">
            <x-section title="Exercises" class="flex-1">
                @foreach ($this->fieldsets as $item)
                    <x-cms::form.fieldset
                        :fieldset="$item"
                        :prefix="$item->prefix ?? 'data'"
                        :showLegend="false"
                    />
                @endforeach
            </x-section>

            <x-section title="Settings" class="w-64 shrink-0">
                <flux:field>
                    <flux:label>Weeks</flux:label>
                    <flux:input wire:model.live="weeks" type="number" min="1" max="52" step="1" />
                </flux:field>
            </x-section>
        </div>

        @if ($this->exercises->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach ($this->exercises as $exercise)
                    <div class="overflow-x-auto min-w-0">
                        <livewire:training.view.plan-exercise-grid
                            :key="'grid-' . $exercise->id . '-' . $weeks"
                            :exercisePlanId="$exerciseProgram->id"
                            :planType="App\Models\ExerciseProgram::class"
                            :exerciseId="$exercise->id"
                            :userId="null"
                            :disabled="false"
                            :weeks="$weeks"
                            :sessionsPerWeek="1"
                            :planMeasuredReps="1"
                            :planMeasuredWeight="50"
                            :planTargetGoal="10"
                        />
                    </div>
                @endforeach
            </div>

            <livewire:training.view.plan-exercise-settings-form />
        @else
            <flux:text class="text-zinc-500">No exercises in this program. Add exercises above to see the training grids.</flux:text>
        @endif
    </div>
</flux:main>
