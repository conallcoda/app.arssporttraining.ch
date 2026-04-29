<?php

namespace App\Cms\Modules;

use App\Livewire\Settings;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class SettingsModule extends Module
{
    public function name(): string
    {
        return 'settings';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('settings-index')
                ->route('/settings')
                ->component(Settings::class),
        ];
    }
}
