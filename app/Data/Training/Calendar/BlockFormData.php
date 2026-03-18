<?php

namespace App\Data\Training\Calendar;

use App\Models\Training\TrainingProgramBlockTypeEnum;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields\Date;
use Coda\Cms\Form\Fields\RadioSegmented;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class BlockFormData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public string $type = 'focus',
        public ?string $start = null,
        public ?string $end = null,
        public string $note = '',
        public ?string $color = 'amber',
    ) {}

    public static function getForm(array $context = []): Form
    {
        $isCategory = ($context['type'] ?? null) === 'category';

        $typeField = RadioSegmented::make('type')
            ->label(__('Type'))
            ->options(
                collect(TrainingProgramBlockTypeEnum::cases())
                    ->reject(fn ($case) => $case === TrainingProgramBlockTypeEnum::Category)
                    ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                    ->all()
            )
            ->default('focus')
            ->live();

        $fields = [
            Date::make('start')->label(__('Start Date'))->required(),
            Date::make('end')->label(__('End Date')),
            Text::make('note')->label(__('Note'))->required(),
        ];

        if (! $isCategory) {
            array_unshift($fields, $typeField);
        }

        return Form::make()
            ->fieldset('Block', $fields)
            ->fieldset(
                'Type Settings',
                function (array $data) use ($context) {
                    $type = TrainingProgramBlockTypeEnum::from($data['type'] ?? 'focus');
                    $fields = $type->blockTypeClass()::fields($context);

                    return $fields ? ['fields' => $fields] : null;
                },
            );
    }
}
