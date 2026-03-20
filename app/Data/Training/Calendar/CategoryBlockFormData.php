<?php

namespace App\Data\Training\Calendar;

use App\Models\Training\TrainingProgramBlockTypeEnum;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Date;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class CategoryBlockFormData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public string $type = 'category',
        public ?string $start = null,
        public ?string $end = null,
        public string $note = '',
        public ?string $color = null,
    ) {}

    public static function getForm(array $context = []): Form
    {
        return Form::make()
            ->fieldset('General', [
                Date::make('start')->label(__('Start Date'))->required(),
                Date::make('end')->label(__('End Date')),
                Text::make('note')->label(__('Name'))->required(),
            ])
            ->fieldset(
                'Settings',
                function (array $data) use ($context) {
                    $type = TrainingProgramBlockTypeEnum::from($data['type'] ?? 'category');
                    $fields = $type->blockTypeClass()::fields($context);

                    return $fields ? ['fields' => $fields] : null;
                },
            );
    }
}
