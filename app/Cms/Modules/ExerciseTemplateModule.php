<?php

namespace App\Cms\Modules;

use App\Livewire\Database\ExerciseTemplateForm;
use App\Livewire\Database\ExerciseTemplateList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ExerciseTemplateModule extends Module
{
    public function name(): string
    {
        return 'exercise-templates';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('exercise-template-index')
                ->route('/exercises/templates')
                ->heading('Exercise Templates')
                ->content(['database.exercise-template-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('exercise-template-list', ExerciseTemplateList::class)
                ->type(ComponentType::List),
            ComponentDefinition::make('exercise-template-form', ExerciseTemplateForm::class)
                ->type(ComponentType::Form),
        ];
    }
}
