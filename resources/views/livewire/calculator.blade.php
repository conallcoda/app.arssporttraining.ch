<div>
    <flux:tab.group>
        <div class="sticky top-0 z-10 bg-white px-3 pt-4 sm:px-6 sm:pt-6 dark:bg-zinc-900">
            <flux:tabs wire:model.live="tab">
                <flux:tab name="calculator">Calculator</flux:tab>
                <flux:tab name="documentation">Documentation</flux:tab>
            </flux:tabs>
        </div>

        <flux:tab.panel name="calculator" class="px-3 sm:px-6">
            <div class="pt-4 sm:pt-6">
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-xl sm:text-2xl font-bold">Training Plan Calculator</h1>
                </div>

                <div class="mb-4 sm:mb-6 grid grid-cols-1 gap-4 sm:gap-6 text-sm md:grid-cols-2">
                    <livewire:calculator.exercise-database />
                    <livewire:calculator.athlete-database />
                </div>

                <livewire:calculator.configuration />
                <livewire:calculator.athlete-training-plan :athletes="$athletes" :exercises="$exercises" :config="$config" />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="documentation" class="px-3 sm:px-6">
            <livewire:calculator-documentation />
        </flux:tab.panel>
    </flux:tab.group>
</div>
