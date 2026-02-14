<?php

namespace App\Cms\Modules;

use App\Livewire\Database\AthleteList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class AthleteModule extends Module
{
    public function name(): string
    {
        return 'athletes';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('athlete-index')
                ->route('/athletes')
                ->title('ARS - Athlete Training // Athletes')
                ->heading('Athletes')
                ->content(['database.athlete-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('athlete-list', AthleteList::class)
                ->type(ComponentType::List),
        ];
    }
}
