<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Cms\Form\Fields\RadioSegmented;
use App\Data\Exercise\ConditioningMode;
use App\Form\Fields\Exercise as Fields;

class ConditioningExerciseConfig extends AbstractConfig
{
    public function __construct(
        public string $mode = 'reps',
        public int $reps = 10,
        public int $duration = 0,
        public int $rest = 30,
    ) {}

    public function badgeFields(): array
    {
        return match ($this->mode) {
            ConditioningMode::Time->value => ['duration', 'rest'],
            default => ['reps', 'rest'],
        };
    }

    public static function getFields(array $data = []): array
    {
        $mode = $data['mode'] ?? ConditioningMode::Reps->value;

        $fields = [
            RadioSegmented::make('mode')->enum(ConditioningMode::class)->live(),
        ];

        if ($mode === ConditioningMode::Time->value) {
            $fields[] = Fields\Duration::make('duration');
        } else {
            $fields[] = Fields\Reps::make('reps');
        }

        $fields[] = Fields\Rest::make('rest');

        return $fields;
    }
}
