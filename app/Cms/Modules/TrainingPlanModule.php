<?php

namespace App\Cms\Modules;

use App\Livewire\Training\TrainingPlanList;
use App\Livewire\Training\TrainingPlanView;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class TrainingPlanModule extends Module
{
    public function name(): string
    {
        return 'training-plans';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('training-plan-index')
                ->route('/training-plans')
                ->title('ARS - Athlete Training // Training Plans')
                ->heading('Training Plans')
                ->content(['training.training-plan-list']),

            PageDefinition::make('training-plan-view')
                ->route('/training-plans/{trainingPlan}')
                ->title('ARS - Athlete Training // Training Plan')
                ->component(TrainingPlanView::class),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('training-plan-list', TrainingPlanList::class)
                ->type(ComponentType::List),
        ];
    }
}
