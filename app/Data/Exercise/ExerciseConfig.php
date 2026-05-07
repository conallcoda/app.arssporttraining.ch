<?php

namespace App\Data\Exercise;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Fields;
use Coda\FormKit\Form;

class ExerciseConfig extends AbstractData
{
    public function __construct(
        /** @var array<string> */
        public array $settings = [],
        /** @var array{sessions: array<int, array{week: int, session: int, data: array<string, mixed>}>, cells: array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>} */
        public array $overrides = ['sessions' => [], 'cells' => []],
        public Settings\SetsSetting $sets = new Settings\SetsSetting,
        public ?Settings\DistanceSetting $distance = null,
        public ?Settings\DurationSetting $duration = null,
        public ?Settings\HeartRateSetting $heartRate = null,
        public ?Settings\HeartRateZoneSetting $heartRateZone = null,
        public ?Settings\NoteSetting $note = null,
        public ?Settings\PaceSetting $pace = null,
        public ?Settings\RepsSetting $reps = null,
        public ?Settings\RestSetting $rest = null,
        public ?Settings\TempoSetting $tempo = null,
        public ?Settings\WattsSetting $watts = null,
        public ?Settings\WeightSetting $weight = null,
        public Settings\PreviewSetting $preview = new Settings\PreviewSetting,
    ) {
        $this->overrides = \App\Support\Training\GridOverrideNormalizer::normalize($this->overrides, [
            'preview' => $this->preview->toArray(),
        ]);

        $settingMap = ExerciseSetting::settingMap();

        foreach ($settingMap as $key => $settingClass) {
            $current = $this->{$key};

            if ($current === null) {
                if (in_array($key, $this->settings, true)) {
                    $this->{$key} = new $settingClass;
                }

                continue;
            }

            $this->{$key} = $current instanceof $settingClass
                ? $settingClass::from($current->toArray())
                : $settingClass::from((array) $current);
        }
    }

    /**
     * @param  array<int, array{label: string, fields: array, prefix: string}>  $prependTabFieldsets
     */
    public static function addFormFieldsets(Form $form, array $prependTabFieldsets = []): void
    {
        $settingNames = [];

        foreach ($prependTabFieldsets as $fieldset) {
            $label = (string) ($fieldset['label'] ?? '');
            $fields = $fieldset['fields'] ?? [];
            $prefix = (string) ($fieldset['prefix'] ?? 'data');

            $form->fieldset(
                $label,
                fn (array $data) => ['fields' => $fields, 'prefix' => $prefix],
            );

            $settingNames[] = $label;
        }

        $form->fieldset(
            'Sets',
            fn (array $data) => ['fields' => Settings\SetsSetting::fields($data), 'prefix' => 'data.config.sets'],
        );

        $settingNames[] = 'sets';

        $settingsField = Fields\Pillbox::make('settings')->label('Settings')->enum(ExerciseSetting::class)->rules('array')->default(['reps'])->live();

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
