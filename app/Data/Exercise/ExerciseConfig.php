<?php

namespace App\Data\Exercise;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Fields;
use Coda\Cms\Form\Form;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        /** @var array<string> */
        public array $settings = [],
        /** @var array{cells: array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>, weeks: array<int, array{week: int, data: array<string, mixed>}>} */
        public array $overrides = ['cells' => [], 'weeks' => []],
        public Settings\SetsSetting $sets = new Settings\SetsSetting,
        public ?Settings\DistanceSetting $distance = null,
        public ?Settings\DurationSetting $duration = null,
        public ?Settings\PaceSetting $pace = null,
        public ?Settings\RepsSetting $reps = null,
        public ?Settings\RestSetting $rest = null,
        public ?Settings\TempoSetting $tempo = null,
        public ?Settings\WattsSetting $watts = null,
        public ?Settings\WeightSetting $weight = null,
        public Settings\PreviewSetting $preview = new Settings\PreviewSetting,
    ) {
        $settingMap = ExerciseSetting::settingMap();

        foreach ($this->settings as $key) {
            if (isset($settingMap[$key]) && $this->{$key} === null) {
                $this->{$key} = new ($settingMap[$key]);
            }
        }
    }

    public static function addFormFieldsets(Form $form): void
    {
        $form->fieldset('Sets', Settings\SetsSetting::fields(), 'data.config.sets');

        $settingNames = ['sets'];

        $settingsField = Fields\Pillbox::make('settings')->label('Settings')->enum(ExerciseSetting::class)->rules('array')->default(['reps', 'weight', 'tempo', 'rest'])->live();

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
