<?php

namespace App\Cms\Modules;

use App\Livewire\Database\ExerciseExternalList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ExerciseExternalModule extends Module
{
    public function name(): string
    {
        return 'exercise-external';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('exercise-external-index')
                ->route('/exercises/import')
                ->title('ARS - Athlete Training // Import Exercises')
                ->heading('Import Exercises')
                ->content(['database.exercise-external-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('exercise-external-list', ExerciseExternalList::class)
                ->type(ComponentType::List),
        ];
    }
}
