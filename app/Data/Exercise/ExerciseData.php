<?php

namespace App\Data\Exercise;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields\Tags;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Exercise as Fields;
use App\Models\Exercise\Exercise;
use Carbon\Carbon;

class ExerciseData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ExerciseType $type,
        public ?ExerciseConfig $config = null,
        public array $categories = [],
        public array $equipment = [],
        public array $modifiers = [],
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromExercise(Exercise $exercise): self
    {
        return new self(
            id: $exercise->id,
            name: $exercise->name,
            type: $exercise->type ?? ExerciseType::Strength,
            config: $exercise->config,
            categories: self::mapTagIds($exercise, 'categories'),
            equipment: self::mapTagIds($exercise, 'equipment'),
            modifiers: self::mapTagIds($exercise, 'modifiers'),
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
                'type' => $this->type,
                'config' => $this->config?->toArray() ?? [],
            ]
        );

        $this->id = $exercise->id;

        $tagIds = collect([
            ...$this->categories,
            ...$this->equipment,
            ...$this->modifiers,
        ])->filter()->all();

        $exercise->tags()->sync($tagIds);
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Fields\ExerciseName::make('name')->required(),
                Fields\ExerciseType::make('type')->required(),
                Tags::make('categories', 'exercise_category')->label('Categories')->asTree()->withOptions(),
                Tags::make('equipment', 'exercise_equipment')->label('Equipment')->withOptions(),
                Tags::make('modifiers', 'exercise_modifiers')->label('Modifiers')->withOptions(),
            ])
            ->fieldset('Defaults', function (array $data): ?array {
                $type = ExerciseType::tryFrom($data['type'] ?? null);
                if (! $type) {
                    return null;
                }

                $accessor = $type->getConfigAccessor();
                $configData = $data['config'][$accessor] ?? [];

                return [
                    'fields' => $type->getFields($configData),
                    'prefix' => 'data.config.'.$accessor,
                ];
            })
            ->discriminator('type', 'config');
    }

    public function getDefaultsBadges(): array
    {
        return $this->config?->toBadges($this->type) ?? [];
    }
}
