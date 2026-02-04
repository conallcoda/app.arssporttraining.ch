<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Form\Fields\Select;
use App\Form\Fields\Text;
use App\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTypeEnum;

class ExerciseData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ExerciseTypeEnum $type,
        public array $typeConfig = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public static function fromExercise(Exercise $exercise): self
    {
        $type = $exercise->type ?? ExerciseTypeEnum::Strength;
        $typeConfig = [];

        $typeFieldsClass = $type->getFieldsClass();
        if ($typeFieldsClass) {
            $typeConfig = $typeFieldsClass::fromExercise($exercise)->toArray();
        }

        return new self(
            id: $exercise->id,
            name: $exercise->name ?? '',
            type: $type,
            typeConfig: $typeConfig,
        );
    }

    public function persist(): void
    {
        $extra = ['type' => $this->typeConfig];

        if ($this->id === null) {
            $exercise = Exercise::create([
                'name' => $this->name,
                'type' => $this->type,
                'extra' => $extra,
            ]);
            $this->id = $exercise->id;
        } else {
            $exercise = Exercise::findOrFail($this->id);
            $exercise->name = $this->name;
            $exercise->type = $this->type;
            $exercise->extra = $extra;
            $exercise->save();
        }
    }

    public static function getFields(): array
    {
        return [
            Text::make('name')
                ->label('Name')
                ->placeholder('Name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
            Select::make('type')
                ->label('Type')
                ->enum(ExerciseTypeEnum::class)
                ->live(),
        ];
    }

    public static function getTypeFieldsClass(ExerciseTypeEnum $type): ?string
    {
        return $type->getFieldsClass();
    }

    public function getDefaultsBadges(): array
    {
        $typeFieldsClass = self::getTypeFieldsClass($this->type);

        if (! $typeFieldsClass) {
            return [];
        }

        $fields = $typeFieldsClass::getFields();
        $badges = [];

        foreach ($fields as $field) {
            $value = $this->typeConfig[$field->name] ?? null;

            if ($value !== null && $value !== '') {
                $displayValue = (string) $value;

                if ($field->suffix) {
                    $displayValue .= ' ' . $field->suffix;
                }

                $badges[] = [
                    'label' => $displayValue,
                    'modalField' => $field->name,
                ];
            }
        }

        return $badges;
    }
}
