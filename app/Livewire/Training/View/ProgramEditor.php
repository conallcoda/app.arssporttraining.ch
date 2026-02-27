<?php

namespace App\Livewire\Training\View;

use App\Data\Training\ProgramData;
use App\Models\ExercisePlanProgram;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\Cms\Livewire\Concerns\InteractsWithParentView;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProgramEditor extends Component
{
    use InteractsWithFormData;
    use InteractsWithParentView;

    public int $exercisePlanId;

    public string $planType = \App\Models\ExercisePlan::class;

    public ?int $programId = null;

    public array $data = [];

    public function mount(int $exercisePlanId, ?int $programId = null): void
    {
        $this->exercisePlanId = $exercisePlanId;
        $this->programId = $programId;

        if ($this->programId !== null) {
            $this->loadProgramData();
        } else {
            $this->data = $this->buildDefaultsFromFieldsets();
        }
    }

    protected function loadProgramData(): void
    {
        $program = ExercisePlanProgram::query()
            ->where('id', $this->programId)
            ->where('plannable_type', $this->planType)
            ->where('plannable_id', $this->exercisePlanId)
            ->with(['exercises' => fn ($q) => $q->orderByPivot('sort')])
            ->firstOrFail();

        $this->data = [
            'name' => $program->name,
            'program_category_id' => $program->program_category_id,
            'exercises' => $program->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                '_key' => uniqid('item_', true),
                'sort' => $exercise->pivot->sort ?? 0,
            ])->values()->all(),
        ];
    }

    #[Computed]
    public function formConfig(): Form
    {
        return ProgramData::getForm();
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    public function saveProgram(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets());

        $this->notifyDataChanged('program', [
            'data' => $this->data,
            'editingProgramId' => $this->programId,
            'assigningWeekId' => null,
            'assigningDay' => null,
            'assigningSlot' => null,
            'userId' => null,
        ]);

        $this->dispatch('portal:close');
    }

    public function render()
    {
        return view('livewire.training.view.program-editor');
    }
}
