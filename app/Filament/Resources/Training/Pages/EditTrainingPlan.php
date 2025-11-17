<?php

namespace App\Filament\Resources\Training\Pages;

use Filament\Resources\Pages\Page;
use App\Models\Training\Periods\TrainingSeason;

class EditTrainingPlan extends Page
{
    protected static string $resource = \App\Filament\Resources\Training\TrainingPlanResource::class;

    public TrainingSeason $record;

    public function mount(int | string | TrainingSeason $record): void
    {
        $this->record = $record instanceof TrainingSeason
            ? $record
            : TrainingSeason::findOrFail($record);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getView(): string
    {
        return 'filament.resources.training.pages.edit-training-plan';
    }
}
