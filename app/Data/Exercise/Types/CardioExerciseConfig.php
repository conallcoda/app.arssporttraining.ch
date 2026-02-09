<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Cms\Form\Fields\RadioSegmented;
use App\Data\Exercise\CardioMode;
use App\Data\Exercise\ExerciseDimensions;
use App\Form\Fields\Exercise as Fields;

class CardioExerciseConfig extends AbstractConfig
{
    public function __construct(
        public string $mode = 'distance',
        public int $distance = 0,
        public int $duration = 0,
        public string $pace = '00:00',
        public int $watts = 0,
    ) {}

    protected function formatBadgeValue(string $field, mixed $value): ?string
    {
        if ($field === 'pace') {
            return $value.' min/km';
        }

        return null;
    }

    /** @return ExerciseDimensions[] */
    public function dimensions(): array
    {
        return match ($this->mode) {
            CardioMode::Time->value => [
                ExerciseDimensions::Duration,
                ExerciseDimensions::Pace,
                ExerciseDimensions::Watts,
            ],
            default => [
                ExerciseDimensions::Distance,
                ExerciseDimensions::Pace,
                ExerciseDimensions::Watts,
            ],
        };
    }

    public function badgeFields(): array
    {
        return match ($this->mode) {
            CardioMode::Time->value => ['duration', 'pace', 'watts'],
            default => ['distance', 'pace', 'watts'],
        };
    }

    public static function getFields(array $data = []): array
    {
        $mode = $data['mode'] ?? CardioMode::Distance->value;

        $fields = [
            RadioSegmented::make('mode')->enum(CardioMode::class)->live(),
        ];

        if ($mode === CardioMode::Time->value) {
            $fields[] = Fields\Duration::make('duration');
        } else {
            $fields[] = Fields\Distance::make('distance');
        }

        $fields[] = Fields\Pace::make('pace');
        $fields[] = Fields\Watts::make('watts');

        return $fields;
    }
}
