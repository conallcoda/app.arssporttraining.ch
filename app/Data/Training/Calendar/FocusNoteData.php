<?php

namespace App\Data\Training\Calendar;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Date;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class FocusNoteData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?string $start = null,
        public ?string $end = null,
        public string $note = '',
    ) {}

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('Focus', [
                Date::make('start')->label(__('Start Date'))->required(),
                Date::make('end')->label(__('End Date')),
                Text::make('note')->label(__('Note'))->required(),
            ]);
    }
}
