<?php

namespace App\Cms\Modules;

use App\Livewire\Database\CoachList;
use App\Livewire\Database\OwnerList;
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
                ->heading('Coaches')
                ->content(['database.coach-list']),
            PageDefinition::make('owner-index')
                ->route('/owners')
                ->heading('Owners')
                ->content(['database.owner-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('coach-list', CoachList::class)
                ->type(ComponentType::List),
            ComponentDefinition::make('owner-list', OwnerList::class)
                ->type(ComponentType::List),
        ];
    }
}
