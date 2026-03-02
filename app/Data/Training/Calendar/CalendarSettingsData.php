<?php

namespace App\Data\Training\Calendar;

use App\Form\Fields\Training\Calendar\Mode;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class CalendarSettingsData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public string $mode = 'month',
    ) {}

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('Settings', [
                Mode::make('mode')->live(),
            ])
            ->fieldset('Month', [], show: 'mode == "month"', view: 'livewire.training.calendar.month-presets')
            ->fieldset('Week', [], show: 'mode == "week"', view: 'livewire.training.calendar.week-presets')
            ->fieldset('Day', [], show: 'mode == "day"', view: 'livewire.training.calendar.day-picker')
            ->fieldset('Range', [], show: 'mode == "range"', view: 'livewire.training.calendar.range-picker');
    }
}
