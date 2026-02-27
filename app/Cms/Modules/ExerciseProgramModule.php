<?php

namespace App\Cms\Modules;

use App\Livewire\Training\ExerciseProgramList;
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
                ->route('/training/exercise-programs')
                ->title('ARS - Athlete Training // Exercise Programs')
                ->heading('Exercise Programs')
                ->content(['training.exercise-program-list']),
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
