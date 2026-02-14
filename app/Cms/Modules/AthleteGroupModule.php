<?php

namespace App\Cms\Modules;

use App\Livewire\Database\AthleteGroupList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class AthleteGroupModule extends Module
{
    public function name(): string
    {
        return 'athlete-groups';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('athlete-group-index')
                ->route('/athletes/groups')
                ->title('ARS - Athlete Training // Athlete Groups')
                ->heading('Athlete Groups')
                ->content(['database.athlete-group-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('athlete-group-list', AthleteGroupList::class)
                ->type(ComponentType::List),
        ];
    }
}
