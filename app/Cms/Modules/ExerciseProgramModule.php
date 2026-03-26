<?php

namespace App\Cms\Modules;

use App\Livewire\Training\ExerciseProgramList;
use App\Livewire\Training\ExerciseProgramView;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ExerciseProgramModule extends Module
{
    public function name(): string
    {
        return 'exercise-programs';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('exercise-program-index')
                ->route('/programs')
                ->heading('Exercise Programs')
                ->content(['training.exercise-program-list']),

            PageDefinition::make('exercise-program-view')
                ->route('/programs/{exerciseProgram}')
                ->component(ExerciseProgramView::class),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('exercise-program-list', ExerciseProgramList::class)
                ->type(ComponentType::List),
        ];
    }
}
