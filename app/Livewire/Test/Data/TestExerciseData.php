<?php

namespace App\Livewire\Test\Data;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;

/**
 * @property array<ExerciseSetting> $settings
 */
class TestExerciseData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public array $settings = [],
    ) {}

    public static function getForm(): Form|array
    {
        $form = Form::make()
            ->fieldset('General', [
                Fields\Text::make('name')->label('Name')->live(),
                Fields\Pillbox::make('settings')->label('Settings')->enum(ExerciseSetting::class)->default([])->live(),
            ]);

        foreach (ExerciseSetting::settingMap() as $settingKey => $settingClass) {
            $form->fieldset(
                $settingClass::getName(),
                fn (array $data) => in_array($settingKey, $data['settings'] ?? [])
                    ? ['fields' => $settingClass::fields(), 'prefix' => "data.{$settingKey}"]
                    : null,
            );
        }

        return $form;
    }
}
