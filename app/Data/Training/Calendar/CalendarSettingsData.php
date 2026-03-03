<?php

namespace App\Data\Training\Calendar;

use App\Form\Fields\Training\Calendar\CalendarPreset;
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
        public ?string $date = null,
        public ?string $start = null,
        public ?string $end = null,
    ) {}

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('Settings', [
                Mode::make('mode')->live(),
            ])
            ->fieldset('Month', [CalendarPreset::month()], show: 'mode == "month"')
            ->fieldset('Week', [CalendarPreset::week()], show: 'mode == "week"')
            ->fieldset('Day', [CalendarPreset::day()], show: 'mode == "day"')
            ->fieldset('Range', [CalendarPreset::range()], show: 'mode == "range"');
    }
}
