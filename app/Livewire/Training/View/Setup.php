<?php

namespace App\Livewire\Training\View;

use App\Data\Form\FluxField;
use App\Livewire\Concerns\InteractsWithParentView;
use App\Models\TrainingPlan;
use App\Support\WeekOptions;
use Livewire\Component;

class Setup extends Component
{
    use InteractsWithParentView;

    public TrainingPlan $trainingPlan;

    public ?string $startDate = null;

    public ?int $duration = null;

    public function mount(
        TrainingPlan $trainingPlan,
        ?string $startDate = null,
        ?int $duration = null,
    ): void {
        $this->trainingPlan = $trainingPlan;
        $this->startDate = $startDate;
        $this->duration = $duration;
    }

    public function updated(string $property): void
    {
        $this->save();
    }

    protected function save(): void
    {
        $this->trainingPlan->start_date = $this->startDate;
        $this->trainingPlan->duration = $this->duration;
        $this->trainingPlan->save();

        $this->notifyChanged('setup');
    }

    public function getFields(): array
    {
        return [
            FluxField::select('startDate')
                ->label('Start Date')
                ->options(WeekOptions::generate())
                ->default(WeekOptions::getCurrentWeekValue())
                ->live(),
            FluxField::text('duration')
                ->label('Duration')
                ->suffix('weeks')
                ->default(5)
                ->live(),
        ];
    }

    public function render()
    {
        return view('livewire.training.view.setup', [
            'fields' => $this->getFields(),
        ]);
    }
}
