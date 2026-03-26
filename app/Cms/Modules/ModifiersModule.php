<?php

namespace App\Cms\Modules;

use App\Livewire\Database\ModifiersList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class ModifiersModule extends Module
{
    public function name(): string
    {
        return 'modifiers';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('modifiers-index')
                ->route('/exercises/modifiers')
                ->heading('Modifiers')
                ->content(['database.modifiers-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('modifiers-list', ModifiersList::class)
                ->type(ComponentType::List),
        ];
    }
}
