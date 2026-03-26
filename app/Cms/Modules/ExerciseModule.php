<?php

namespace App\Cms\Modules;

use App\Livewire\Database\ExerciseForm;
use App\Livewire\Database\ExerciseList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ExerciseModule extends Module
{
    public function name(): string
    {
        return 'exercises';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('exercise-index')
                ->route('/exercises')
                ->heading('Exercises')
                ->content(['database.exercise-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('exercise-list', ExerciseList::class)
                ->type(ComponentType::List),
            ComponentDefinition::make('exercise-form', ExerciseForm::class)
                ->type(ComponentType::Form),
        ];
    }
}
