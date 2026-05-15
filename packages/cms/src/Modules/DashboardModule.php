<?php

namespace Coda\Cms\Modules;

use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Livewire\Dashboard;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class DashboardModule extends Module
{
    public function name(): string
    {
        return 'dashboard';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('dashboard')
                ->route('/dashboard')
                ->heading('Dashboard')
                ->content(['cms.dashboard']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('dashboard', Dashboard::class)
                ->type(ComponentType::Custom),
        ];
    }
}
