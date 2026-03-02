<x-slot:navbar>
    <x-top-nav>
        <flux:navbar.item href="/programs">Programs</flux:navbar.item>
        <span class="text-zinc-400 dark:text-zinc-500">/</span>
        <flux:navbar.item current>{{ $exerciseProgram->name }}</flux:navbar.item>
    </x-top-nav>
</x-slot:navbar>

<flux:main>
    <x-editable-title :name="$exerciseProgram->name" />

    <livewire:training.view.program-editor
        :exerciseProgram="$exerciseProgram"
        :planId="$exerciseProgram->id"
        :planType="App\Models\Exercise\ExerciseProgram::class"
        :showWeeksInput="true"
        wire:key="editor-{{ $exerciseProgram->id }}"
    />
</flux:main>
