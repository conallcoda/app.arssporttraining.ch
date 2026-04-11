<?php

namespace App\Data\Exercise;

use App\Form\Fields\Category;
use App\Models\Exercise\ExerciseExternal;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Fields\Tags;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields;
use Coda\FormKit\Form;

class ExerciseExternalData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $source = null,
        public ?int $category = null,
        /** @var list<string> */
        public array $categoryPath = [],
        public array $equipment = [],
        public array $modifiers = [],
        public ?string $videoUrl = null,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromExerciseExternal(ExerciseExternal $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            source: $model->source,
            category: $model->category_id,
            categoryPath: self::buildCategoryPath($model),
            equipment: self::mapTagIds($model, 'equipment'),
            modifiers: self::mapTagIds($model, 'modifiers'),
            videoUrl: $model->video_url,
            updatedAt: $model->updated_at,
        );
    }

    /** @return list<string> */
    private static function buildCategoryPath(ExerciseExternal $model): array
    {
        if (! $model->relationLoaded('category') || $model->category === null) {
            return [];
        }

        $category = $model->category;

        if (! $category->relationLoaded('ancestorsAndSelf')) {
            return [$category->name];
        }

        return $category->ancestorsAndSelf
            ->sortBy('depth')
            ->pluck('name')
            ->values()
            ->all();
    }

    /** @return list<int> */
    private static function mapTagIds(ExerciseExternal $model, string $relation): array
    {
        if (! $model->relationLoaded($relation)) {
            return [];
        }

        return $model->{$relation}->pluck('id')->all();
    }

    public function persist(): void
    {
        $exercise = ExerciseExternal::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'video_url' => $this->videoUrl,
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
        return Form::make()
            ->fieldset('General', [
                Fields\Text::make('name')->label('Name'),
                Category::make('category', 'exercise_category')->label('Category')->withOptions(),
                Tags::make('equipment', 'exercise_equipment')->label('Equipment')->withOptions()->create(),
                Tags::make('modifiers', 'exercise_modifiers')->label('Modifiers')->withOptions()->create(),
                Fields\Url::make('videoUrl')->label('Video URL')->placeholder('https://'),
            ]);
    }
}
