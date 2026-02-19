<div>
    <x-training.exercise-grid
        :grid="$this->previewGrid"
        :name="$exerciseName"
        :summary="$this->previewGrid->summary"
        :badges="$this->badges"
    />
</div>
