<?php

namespace App\Data\Training\Calendar;

use App\Models\Training\TrainingProgramBlockTypeEnum;
use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields\Date;
use Coda\FormKit\Fields\RadioSegmented;
use Coda\FormKit\Fields\Text;
use Coda\FormKit\Form;

class NoteBlockFormData extends AbstractData implements HasForms
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
        return Form::make()
            ->fieldset('General', [
                RadioSegmented::make('type')
                    ->label(__('Type'))
                    ->options(
                        collect(TrainingProgramBlockTypeEnum::cases())
                            ->reject(fn ($case) => $case === TrainingProgramBlockTypeEnum::Category)
                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                            ->all()
                    )
                    ->default('focus')
                    ->live(),
                Date::make('start')->label(__('Start Date'))->required(),
                Date::make('end')->label(__('End Date')),
                Text::make('note')->label(__('Note'))->required(),
            ])
            ->fieldset(
                'Settings',
                function (array $data) use ($context) {
                    $type = TrainingProgramBlockTypeEnum::from($data['type'] ?? 'focus');
                    $fields = $type->blockTypeClass()::fields($context);

                    return $fields ? ['fields' => $fields] : null;
                },
            );
    }
}
