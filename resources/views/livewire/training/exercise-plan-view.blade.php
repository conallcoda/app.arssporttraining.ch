<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/programs/plans">{{ __('Plans') }}</flux:navbar.item>
        <span class="text-zinc-400 dark:text-zinc-500">/</span>
        <flux:navbar.item current>{{ $exercisePlan->name }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <x-editable-title :name="$exercisePlan->name" />

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="schedule">{{ __('Schedule') }}</flux:tab>
            <flux:tab name="plan">{{ __('Plan') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="schedule">
            <livewire:training.view.schedule :exercise-plan="$exercisePlan" :programs="$programs" :users="$users"
                wire:key="schedule-{{ $this->getDataKey() }}" />
        </flux:tab.panel>

        <flux:tab.panel name="plan">
            <livewire:training.view.plan :exercise-plan="$exercisePlan" :programs="$programs" :users="$users"
                wire:key="plan-{{ $this->getDataKey() }}" />
        </flux:tab.panel>

    </flux:tab.group>
</flux:main>
