<?php

namespace App\Data\Exercise;

use App\Data\AbstractData;
use App\Form\Concerns\InteractsWithForms;
use App\Form\Fields\Exercise as Fields;
use App\Form\Form;
use App\Models\Contracts\HasForms;
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
        $config = $this->config?->toArray() ?? [];
        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'config' => $config,
        ];

        if (! isset($this->id)) {
            $exercise = Exercise::create($data);
            $this->id = $exercise->id;
        } else {
            $exercise = Exercise::findOrFail($this->id);
            $exercise->update($data);
            $exercise->save();
        }
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->withFields([
                Fields\ExerciseName::make('name'),
                Fields\ExerciseType::make('type'),
            ])
            ->fieldset('general', 'General', ['name', 'type'])
            ->fieldset('defaults', function (array $data): ?array {
                $type = ExerciseType::tryFrom($data['type'] ?? null);
                if (! $type) {
                    return null;
                }

                return [
                    'label' => 'Defaults',
                    'fields' => $type->getFields(),
                    'prefix' => 'data.config.'.$type->getConfigAccessor(),
                ];
            })
            ->discriminator('type', 'config');
    }

    public function getDefaultsBadges(): array
    {
        return $this->config?->toBadges($this->type) ?? [];
    }
}
