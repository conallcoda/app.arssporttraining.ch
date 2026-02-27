<?php

namespace App\Cms\Modules;

use App\Livewire\Training\ExercisePlanList;
use App\Livewire\Training\ExercisePlanView;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ExercisePlanModule extends Module
{
    public function name(): string
    {
        return 'exercise-plans';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('exercise-plan-index')
                ->route('/exercise-plans')
                ->title('ARS - Athlete Training // Exercise Plans')
                ->heading('Exercise Plans')
                ->content(['training.exercise-plan-list']),

            PageDefinition::make('exercise-plan-view')
                ->route('/exercise-plans/{exercisePlan}')
                ->title('ARS - Athlete Training // Exercise Plan')
                ->component(ExercisePlanView::class),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('exercise-plan-list', ExercisePlanList::class)
                ->type(ComponentType::List),
        ];
    }
}
