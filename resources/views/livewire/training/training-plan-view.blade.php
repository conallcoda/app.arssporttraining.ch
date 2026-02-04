<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/training-plans">Training Plans</flux:navbar.item>
        <span class="text-zinc-400 dark:text-zinc-500">/</span>
        <flux:navbar.item current>{{ $trainingPlan->name }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <div
        x-data="{
            editing: false,
            name: @js($trainingPlan->name),
            originalName: @js($trainingPlan->name),
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
        }"
        class="mb-6 group"
    >
        <div x-show="!editing" @click="startEditing()" class="flex items-center gap-2 cursor-pointer">
            <flux:heading size="xl" level="1" class="hover:text-zinc-600 dark:hover:text-zinc-300">
                <span x-text="name"></span>
            </flux:heading>
            <flux:icon.pencil class="w-5 h-5 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" />
        </div>
        <input
            x-show="editing"
            x-cloak
            x-ref="input"
            x-model="name"
            @keydown.enter.prevent="save()"
            @keydown.escape.prevent="cancel()"
            @blur="save()"
            type="text"
            class="text-2xl font-semibold bg-transparent border-b-2 border-zinc-300 dark:border-zinc-600 focus:border-blue-500 focus:outline-none w-full max-w-lg text-zinc-800 dark:text-white"
        />
    </div>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="athletes">Athletes</flux:tab>
            <flux:tab name="programs">Programs</flux:tab>
            <flux:tab name="schedule">Schedule</flux:tab>
            <flux:tab name="plan">Plan</flux:tab>
            <flux:tab name="export">Export</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="athletes">
            <livewire:training.view.athletes
                :training-plan="$trainingPlan"
                :user-ids="$userIds"
                :user-group-ids="$userGroupIds"
                wire:key="athletes-{{ $this->getDataKey('athletes') }}"
            />
        </flux:tab.panel>

        <flux:tab.panel name="programs">
            <livewire:training.view.programs
                :training-plan="$trainingPlan"
                :programs="$programs"
                wire:key="programs-{{ $this->getDataKey('programs') }}"
            />
        </flux:tab.panel>

        <flux:tab.panel name="schedule">
            <livewire:training.view.schedule
                :training-plan="$trainingPlan"
                :programs="$programs"
                wire:key="schedule-{{ $this->getDataKey('programs') }}"
            />
        </flux:tab.panel>

        <flux:tab.panel name="plan">
            <livewire:training.view.plan
                :training-plan="$trainingPlan"
                :programs="$programs"
                :users="$users"
                wire:key="plan-{{ $this->getDataKey() }}"
            />
        </flux:tab.panel>

        <flux:tab.panel name="export">
            <livewire:training.view.export
                :training-plan="$trainingPlan"
                :programs="$programs"
                :users="$users"
                wire:key="export-{{ $this->getDataKey() }}"
            />
        </flux:tab.panel>
    </flux:tab.group>
</flux:main>
