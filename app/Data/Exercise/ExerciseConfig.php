<?php

namespace App\Data\Exercise;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Fields;
use App\Cms\Form\Form;
use App\Data\Exercise\Settings\SetsSetting;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        /** @var array<string> */
        public array $settings = [],
        /** @var array{cells: array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>, weeks: array<int, array{week: int, data: array<string, mixed>}>} */
        public array $overrides = ['cells' => [], 'weeks' => []],
    ) {}

    public static function addFormFieldsets(Form $form): void
    {
        $form->fieldset('Sets', SetsSetting::fields(), 'data.config.sets');

        $settingNames = ['sets'];

        $settingsField = Fields\Pillbox::make('settings')->label('Settings')->enum(ExerciseSetting::class)->default(['reps', 'weight', 'tempo', 'rest'])->live();

        foreach (ExerciseSetting::settingMap() as $settingKey => $settingClass) {
            $form->fieldset(
                $settingClass::getName(),
                fn (array $data) => in_array($settingKey, $data['config']['settings'] ?? [])
                    ? ['fields' => $settingClass::fields(), 'prefix' => "data.config.{$settingKey}"]
                    : null,
            );
            $settingNames[] = $settingClass::getName();
        }

        $form->fieldsetTabs($settingNames, 'Settings', sortByDataKey: 'config.settings', headerFields: [
            $settingsField,
        ], headerPrefix: 'data.config');
    }
}
