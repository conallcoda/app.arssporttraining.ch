<div>
    <flux:tab.group>
        <div class="sticky top-0 z-10 bg-white px-3 pt-4 sm:px-6 sm:pt-6 dark:bg-zinc-900">
            <flux:tabs wire:model.live="tab">
                <flux:tab name="training">Training</flux:tab>
                <flux:tab name="exercises">Programs</flux:tab>
                <flux:tab name="athletes">Athletes</flux:tab>
                <flux:tab name="export">Export</flux:tab>
            </flux:tabs>
        </div>

        <flux:tab.panel name="training" class="px-3 sm:px-6">
            <div class="">
                <livewire:calculator.configuration wire:key="configuration" />
                <livewire:calculator.athlete-training-plan :athletes="$athletes" :exercises="$exercises" :config="$config"
                    wire:key="athlete-training-plan-{{ $this->getDataKey() }}" />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="exercises" class="px-3 sm:px-6">
            <div class="">
                <livewire:calculator.exercise-database wire:key="exercise-database" />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="athletes" class="px-3 sm:px-6">
            <div class="">
                <livewire:calculator.athlete-list wire:key="athlete-list" />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="export" class="px-3 sm:px-6">
            <div class="">
                <livewire:calculator.export :athletes="$athletes" :exercises="$exercises" :config="$config"
                    wire:key="export-{{ $this->getDataKey() }}" />
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</div>
