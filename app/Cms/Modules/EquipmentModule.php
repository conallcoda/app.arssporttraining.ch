<?php

namespace App\Cms\Modules;

use App\Livewire\Database\EquipmentList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class EquipmentModule extends Module
{
    public function name(): string
    {
        return 'equipment';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('equipment-index')
                ->route('/exercises/equipment')
                ->heading('Equipment')
                ->content(['database.equipment-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('equipment-list', EquipmentList::class)
                ->type(ComponentType::List),
        ];
    }
}
