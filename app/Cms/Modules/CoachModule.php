<?php

namespace App\Cms\Modules;

use App\Livewire\Database\CoachList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class CoachModule extends Module
{
    public function name(): string
    {
        return 'coaches';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('coach-index')
                ->route('/coaches')
                ->title('ARS - Athlete Training // Coaches')
                ->heading('Coaches')
                ->content(['database.coach-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('coach-list', CoachList::class)
                ->type(ComponentType::List),
        ];
    }
}
