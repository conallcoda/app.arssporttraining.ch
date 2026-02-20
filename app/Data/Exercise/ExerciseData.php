<?php

namespace App\Data\Exercise;

use App\Models\Exercise\Exercise;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExerciseData extends AbstractData implements HasForms
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
        public ExerciseConfig $config = new ExerciseConfig,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromImport(ExerciseImportData $importData): self
    {
        $config = new ExerciseConfig(
            settings: ['reps', 'weight', 'tempo', 'rest'],
        );

        foreach ($config->settings as $settingKey) {
            $setting = ExerciseSetting::tryFrom($settingKey);

            if ($setting && $settingClass = $setting->settingClass()) {
                $config->{$settingKey} = new $settingClass;
            }
        }

        return new self(
            id: null,
            name: $importData->name,
            category: $importData->category,
            equipment: $importData->equipment,
            modifiers: $importData->modifiers,
            videoUrl: $importData->videoUrl,
            instructions: $importData->instructions,
            config: $config,
        );
    }

    public static function fromExercise(Exercise $exercise): self
    {
        return new self(
            id: $exercise->id,
            name: $exercise->name,
            category: $exercise->category_id,
            equipment: self::mapTagIds($exercise, 'equipment'),
            modifiers: self::mapTagIds($exercise, 'modifiers'),
            videoUrl: $exercise->video_url,
            instructions: $exercise->instructions,
            config: $exercise->config ?? new ExerciseConfig,
            updatedAt: $exercise->updated_at,
        );
    }

    /** @return list<int> */
    private static function mapTagIds(Exercise $exercise, string $relation): array
    {
        if (! $exercise->relationLoaded($relation)) {
            return [];
        }

        return $exercise->{$relation}->pluck('id')->all();
    }

    public function persist(): void
    {
        $exercise = Exercise::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'video_url' => $this->videoUrl,
                'instructions' => $this->instructions,
                'config' => $this->config?->toArray() ?? [],
                'category_id' => $this->category,
            ]
        );

        $this->id = $exercise->id;

        $tagIds = collect([
            ...$this->equipment,
            ...$this->modifiers,
        ])->filter()->all();

        $exercise->tags()->sync($tagIds);
    }

    public static function getForm(): Form
    {
        $form = Form::make()
            ->fieldset('General', [
                Fields\Text::make('name'),
                Fields\Category::make('category', 'exercise_category')->label('Category')->withOptions(),
                Fields\Tags::make('equipment', 'exercise_equipment')->label('Equipment')->withOptions()->create(),
                Fields\Tags::make('modifiers', 'exercise_modifiers')->label('Modifiers')->withOptions()->create(),
            ])
            ->fieldset('Instructions', [
                Fields\Url::make('videoUrl')->label('Video URL')->placeholder('https://'),
                Fields\Textarea::make('instructions')->label('Instructions')->placeholder('Enter exercise instructions...'),
            ]);

        ExerciseConfig::addFormFieldsets($form);

        $form->fieldsetTabs(['General', 'Instructions', 'Settings']);

        return $form;
    }

    public function getDefaultsBadges(): array
    {
        if (empty($this->config->settings)) {
            return [];
        }

        return collect($this->config->settings)
            ->map(fn (string $setting) => ['label' => ExerciseSetting::tryFrom($setting)?->label() ?? ucfirst($setting), 'modalField' => $setting])
            ->values()
            ->all();
    }
}
