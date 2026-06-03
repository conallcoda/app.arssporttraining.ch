<?php

namespace App\Data\Exercise;

use App\Form\Fields\Category;
use App\Form\Fields\Owner;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTemplate;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Fields\Tags;
use Coda\Cms\Models\Contracts\PersistsWithMedia;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields;
use Coda\FormKit\Fields\FileUpload;
use Coda\FormKit\Form;
use Illuminate\Database\Eloquent\Model;

class ExerciseData extends AbstractData implements HasForms, PersistsWithMedia
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?int $category = null,
        public ?string $categoryName = null,
        public ?string $categoryColor = null,
        public array $equipment = [],
        public array $modifiers = [],
        public array $internalTags = [],
        public ?string $videoUrl = null,
        public ?string $instructions = null,
        public ExerciseConfig $config = new ExerciseConfig,
        public ?Carbon $updatedAt = null,
        public ?int $template = null,
        public ?int $owner_id = null,
        public ?string $ownerName = null,
        public ?string $ownerColor = null,
    ) {}

    public static function fromImport(ExerciseImportData $importData): self
    {
        return new self(
            id: null,
            name: $importData->name,
            category: $importData->category,
            equipment: $importData->equipment,
            modifiers: $importData->modifiers,
            videoUrl: $importData->videoUrl,
            instructions: $importData->instructions,
            config: $importData->config,
            template: $importData->template,
        );
    }

    public static function fromExercise(Exercise $exercise): self
    {
        return new self(
            id: $exercise->id,
            name: $exercise->name,
            category: $exercise->category_id,
            categoryName: $exercise->category?->name,
            categoryColor: $exercise->category?->color,
            equipment: self::mapTagIds($exercise, 'equipment'),
            modifiers: self::mapTagIds($exercise, 'modifiers'),
            internalTags: self::mapTagIds($exercise, 'internalTags'),
            videoUrl: $exercise->video_url,
            instructions: $exercise->instructions,
            config: $exercise->config ?? new ExerciseConfig,
            updatedAt: $exercise->updated_at,
            template: $exercise->template_id,
            owner_id: $exercise->owner_id ?? 0,
            ownerName: $exercise->relationLoaded('owner') ? $exercise->owner?->name : null,
            ownerColor: $exercise->relationLoaded('owner') ? $exercise->owner?->color : null,
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

    public static function resolveModel(int $id): ?Model
    {
        return Exercise::find($id);
    }

    public function persist(): Model
    {
        $exercise = Exercise::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'video_url' => $this->videoUrl,
                'instructions' => $this->instructions,
                'config' => $this->config?->toArray() ?? [],
                'category_id' => $this->category,
                'template_id' => $this->template,
                'owner_id' => $this->owner_id,
            ]
        );

        $this->id = $exercise->id;

        $tagIds = collect([
            ...$this->equipment,
            ...$this->modifiers,
            ...$this->internalTags,
        ])->filter()->all();

        $exercise->tags()->sync($tagIds);

        return $exercise;
    }

    public static function getForm(): Form
    {
        $form = Form::make()
            ->fieldset('General', [
                Owner::make('owner_id')->withOptions()->allowNoOwner(),
                Fields\Text::make('name')->required(true),
                Category::make('category', 'exercise_category')->label('Category')->required()->withOptions(),
                Fields\Select::make('template')
                    ->label('Template')
                    ->options(ExerciseTemplate::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->variant('listbox')
                    ->searchable()
                    ->clearable()
                    ->live(),
                Tags::make('equipment', 'exercise_equipment')->label('Equipment')->withOptions()->create(),
                Tags::make('modifiers', 'exercise_modifiers')->label('Modifiers')->withOptions()->create(),
                Tags::make('internalTags', 'exercise_internal')->label('Tags')->withOptions()->create(),
            ])
            ->fieldset('Instructions', [
                FileUpload::make('photos')
                    ->label('Photos')
                    ->multiple()
                    ->collection('photos')
                    ->dropzoneText('JPG, PNG up to 10MB'),
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
            ->filter(fn (string $setting) => $this->config->{$setting} !== null)
            ->flatMap(fn (string $setting) => $this->config->{$setting}->badges())
            ->values()
            ->all();
    }
}
