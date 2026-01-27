<?php

namespace App\Livewire\Training\View;

use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanProgram;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Programs extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public string $programName = '';

    public ?int $editingProgramId = null;

    public ?int $deletingProgramId = null;

    public function mount(TrainingPlan $trainingPlan): void
    {
        $this->trainingPlan = $trainingPlan;
    }

    #[Computed]
    public function programs(): array
    {
        return $this->trainingPlan->programs()->with('exercises')->orderBy('sort')->get()->all();
    }

    #[On('training-programs-updated')]
    public function refreshPrograms(): void
    {
        unset($this->programs);
    }

    public function openAddProgramModal(): void
    {
        $this->programName = '';
        $this->editingProgramId = null;
        Flux::modal('add-program')->show();
    }

    public function openEditProgramModal(int $id): void
    {
        $program = TrainingPlanProgram::findOrFail($id);
        $this->programName = $program->name;
        $this->editingProgramId = $id;
        Flux::modal('add-program')->show();
    }

    public function saveProgram(): void
    {
        $this->validate([
            'programName' => 'required|string|min:1',
        ]);

        if ($this->editingProgramId) {
            $program = TrainingPlanProgram::findOrFail($this->editingProgramId);
            $program->name = $this->programName;
            $program->save();
        } else {
            $maxSort = TrainingPlanProgram::where('training_plan_id', $this->trainingPlan->id)->max('sort') ?? -1;
            TrainingPlanProgram::create([
                'training_plan_id' => $this->trainingPlan->id,
                'name' => $this->programName,
                'sort' => $maxSort + 1,
            ]);
        }

        $this->programName = '';
        $this->editingProgramId = null;
        unset($this->programs);

        Flux::modal('add-program')->close();
    }

    public function confirmDeleteProgram(int $id): void
    {
        $this->deletingProgramId = $id;
        Flux::modal('delete-program')->show();
    }

    public function deleteProgram(): void
    {
        if ($this->deletingProgramId) {
            TrainingPlanProgram::where('id', $this->deletingProgramId)->delete();
            $this->deletingProgramId = null;
            unset($this->programs);
        }

        Flux::modal('delete-program')->close();
    }

    public function reorderPrograms(int $sourceId, int $targetId): void
    {
        $programs = $this->trainingPlan->programs()->orderBy('sort')->get();
        $sourceProgram = $programs->firstWhere('id', $sourceId);
        $targetProgram = $programs->firstWhere('id', $targetId);

        if (! $sourceProgram || ! $targetProgram) {
            return;
        }

        $sourceIndex = $programs->search(fn ($p) => $p->id === $sourceId);
        $targetIndex = $programs->search(fn ($p) => $p->id === $targetId);

        $reordered = $programs->values();
        $reordered->splice($sourceIndex, 1);
        $reordered->splice($targetIndex, 0, [$sourceProgram]);

        foreach ($reordered as $index => $program) {
            $program->sort = $index;
            $program->save();
        }

        unset($this->programs);
    }

    public function render()
    {
        return view('livewire.training.view.programs');
    }
}
