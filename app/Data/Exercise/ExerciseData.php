<?php

namespace App\Data\Exercise;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\Exercise as Fields;
use App\Models\Exercise\Exercise;

class ExerciseData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ExerciseType $type,
        public ?ExerciseConfig $config = null,
    ) {}

    public static function fromExercise(Exercise $exercise): self
    {
        return new self(
            id: $exercise->id,
            name: $exercise->name,
            type: $exercise->type ?? ExerciseType::Strength,
            config: $exercise->config,
        );
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
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Fields\ExerciseName::make('name'),
                Fields\ExerciseType::make('type'),
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
