<?php

namespace App\Cms\Modules;

use App\Livewire\Training\ProgramCategoryList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ProgramCategoryModule extends Module
{
    public function name(): string
    {
        return 'program-categories';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('program-category-index')
                ->route('/programs/categories')
                ->title('ARS - Athlete Training // Program Categories')
                ->heading('Program Categories')
                ->content(['training.program-category-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('program-category-list', ProgramCategoryList::class)
                ->type(ComponentType::List),
        ];
    }
}
