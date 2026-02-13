<?php

namespace App\Livewire\Test\Data;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Exercise as ExerciseFields;

/**
 * @property array<ExerciseSetting> $settings
 */
class TestExerciseData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?int $category = null,
        public array $equipment = [],
        public array $modifiers = [],
        public ?string $videoUrl = null,
        public ?string $instructions = null,
        public array $settings = [],
        /** @var array{cells: array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>, weeks: array<int, array{week: int, data: array<string, mixed>}>} */
        public array $overrides = ['cells' => [], 'weeks' => []],
    ) {}

    public static function getForm(): Form|array
    {
        $form = Form::make()
            ->fieldset('General', [
                Fields\Text::make('name')->live(),
                Fields\Category::make('category', 'exercise_category')->label('Category')->withOptions(),
                Fields\Tags::make('equipment', 'exercise_equipment')->label('Equipment')->withOptions(),
                Fields\Tags::make('modifiers', 'exercise_modifiers')->label('Modifiers')->withOptions(),
            ])
            ->fieldset('Instructions', [
                Fields\Url::make('videoUrl')->label('Video URL')->placeholder('https://'),
                Fields\Textarea::make('instructions')->label('Instructions')->placeholder('Enter exercise instructions...'),
            ])
            ->fieldset('Sets', Settings\SetsSetting::fields(), 'data.sets');

        $settingNames = ['sets'];

        $settingsField = Fields\Pillbox::make('settings')->label('Settings')->enum(ExerciseSetting::class)->default(['reps', 'weight', 'tempo', 'rest'])->live();

        foreach (ExerciseSetting::settingMap() as $settingKey => $settingClass) {
            $form->fieldset(
                $settingClass::getName(),
                fn(array $data) => in_array($settingKey, $data['settings'] ?? [])
                    ? ['fields' => $settingClass::fields(), 'prefix' => "data.{$settingKey}"]
                    : null,
            );
            $settingNames[] = $settingClass::getName();
        }

        $form->fieldsetTabs($settingNames, 'Settings', sortByDataKey: 'settings', headerFields: [
            $settingsField,
        ]);

        $form->fieldsetTabs(['General', 'Instructions', 'Settings']);

        return $form;
    }
}
