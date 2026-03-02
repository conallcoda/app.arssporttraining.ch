<?php

namespace App\Cms\Modules;

use App\Livewire\Training\CalendarIndex;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class CalendarModule extends Module
{
    public function name(): string
    {
        return 'calendar';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('calendar-index')
                ->route('/calendar')
                ->title('ARS - Athlete Training // Calendar')
                ->component(CalendarIndex::class),
        ];
    }
}
