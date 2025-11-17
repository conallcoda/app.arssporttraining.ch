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
        if ($record instanceof TrainingSeason) {
            $this->record = $record->load('children.children.children');
        } else {
            $this->record = TrainingSeason::with('children.children.children')
                ->findOrFail($record);
        }
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getView(): string
    {
        return 'filament.resources.training.pages.edit-training-plan';
    }

    public function getNodes(): array
    {
        return [$this->buildTreeNode($this->record)];
    }

    protected function buildTreeNode($period): array
    {
        $node = [
            'id' => $period->id,
            'label' => $period->name ?? $period->type,
        ];

        if ($period->children && $period->children->count() > 0) {
            $node['children'] = $period->children->map(function ($child) {
                return $this->buildTreeNode($child);
            })->toArray();
        }

        return $node;
    }
}
