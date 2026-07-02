<?php

namespace Coda\Cms\Modules;

use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Livewire\UserList;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class UserModule extends Module
{
    public function name(): string
    {
        return 'users';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('user-index')
                ->route('/users')
                ->heading('Users')
                ->content(['cms.user-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('user-list', UserList::class)
                ->type(ComponentType::List),
        ];
    }
}
