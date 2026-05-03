<div>
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
</div>
