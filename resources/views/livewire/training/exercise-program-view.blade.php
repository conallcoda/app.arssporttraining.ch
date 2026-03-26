<x-slot:navbar>
    <x-cms::top-nav>
        <flux:navbar.item :href="route('exercise-program-index')">{{ __('Programs') }}</flux:navbar.item>
        <span class="text-zinc-400 dark:text-zinc-500">/</span>
        <flux:navbar.item current>{{ $exerciseProgram->name }}</flux:navbar.item>
    </x-cms::top-nav>
</x-slot:navbar>

<flux:main>
    <x-cms::editable-title :name="$exerciseProgram->name" />

    <livewire:training.view.program-editor
        :exerciseProgram="$exerciseProgram"
        :planId="$exerciseProgram->id"
        :planType="App\Models\Exercise\ExerciseProgram::class"
        :showWeeksInput="true"
        :planMeasuredReps="1"
        :planMeasuredWeight="50"
        :planTargetGoal="10"
        wire:key="editor-{{ $exerciseProgram->id }}"
    />
</flux:main>
