<?php

namespace App\Data\Training\Calendar;

use App\Form\Fields\Training\Calendar\WeekSlotProgram;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class WeekSlotData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $training_program_id = null,
        public string $start_time = '09:00',
    ) {}

    public static function getForm(?int $groupId = null, ?int $userId = null, ?string $date = null): Form
    {
        return Form::make()
            ->fieldset('Add Slot', [
                WeekSlotProgram::make('training_program_id')->withOptions($groupId, $userId, $date),
            ]);
    }
}
