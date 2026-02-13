<?php

namespace App\Livewire\Test\Data;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;

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
        public TestExerciseDataConfig $config = new TestExerciseDataConfig,
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
            ]);

        TestExerciseDataConfig::addFormFieldsets($form);

        $form->fieldsetTabs(['General', 'Instructions', 'Settings']);

        return $form;
    }
}
