<div>
    <x-cms::editable-title :name="$exerciseProgram->name" />

    <livewire:training.view.program-editor
        :exerciseProgram="$exerciseProgram"
        :planId="$exerciseProgram->id"
        :showWeeksInput="true"
        :planMeasuredReps="1"
        :planMeasuredWeight="50"
        :planTargetGoal="10"
        :planGroupMemberMetrics="[]"
        wire:key="editor-{{ $exerciseProgram->id }}"
    />
</div>
