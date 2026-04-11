<?php

namespace App\Data\Training\Calendar;

use App\Form\Fields\Training\Calendar\CalendarPreset;
use App\Form\Fields\Training\Calendar\TimePeriod;
use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Form;

class CalendarSettingsData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public string $period = 'month',
        public ?string $date = null,
        public ?string $start = null,
        public ?string $end = null,
    ) {}

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('Settings', [
                TimePeriod::make('period')->live(),
            ])
            ->fieldset('Month', [CalendarPreset::month()], show: 'period == "month"')
            ->fieldset('Week', [CalendarPreset::week()], show: 'period == "week"')
            ->fieldset('Day', [CalendarPreset::day()], show: 'period == "day"')
            ->fieldset('Range', [CalendarPreset::range()], show: 'period == "range"');
    }
}
