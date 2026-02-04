<?php

namespace App\Data\Exercise;

use App\Data\AbstractConfig;
use App\Data\AbstractData;
use App\Data\Casts\ConfigCast;
use App\Form\Fields\Exercise as Fields;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;
use Spatie\LaravelData\Attributes\WithCast;

class ExerciseData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ExerciseType $type,
        #[WithCast(ConfigCast::class)]
        public ?AbstractConfig $config = null,
    ) {}

    public static function fromExercise(Exercise $exercise): self
    {
        $type = $exercise->type ?? ExerciseType::Strength;

        $config = match ($type) {
            ExerciseType::Strength => Types\StrengthExerciseConfig::fromExercise($exercise),
            ExerciseType::Cardio => Types\CardioExerciseConfig::fromExercise($exercise),
        };

        return new self(
            id: $exercise->id,
            name: $exercise->name,
            type: $type,
            config: $config,
        );
    }

    public function persist(): void
    {
        $config = $this->config?->toArray() ?? [];

        if ($this->id === null) {
            $exercise = Exercise::create([
                'name' => $this->name,
                'type' => $this->type,
                'config' => $config,
            ]);
            $this->id = $exercise->id;
        } else {
            $exercise = Exercise::findOrFail($this->id);
            $exercise->name = $this->name;
            $exercise->type = $this->type;
            $exercise->config = $config;
            $exercise->save();
        }
    }

    public static function getFields(): array
    {
        return [
            Fields\ExerciseName::make('name'),
            Fields\ExerciseType::make('type'),
        ];
    }

    public static function getFieldsets(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'fields' => ['name', 'type'],
            ],
        ];
    }

    public function getDefaultsBadges(): array
    {
        return $this->config?->toBadges() ?? [];
    }
}
