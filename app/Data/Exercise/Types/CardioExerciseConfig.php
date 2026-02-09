<?php

namespace App\Data\Exercise\Types;

use App\Cms\Data\AbstractConfig;
use App\Cms\Form\Fields\RadioSegmented;
use App\Data\Exercise\CardioMode;
use App\Form\Fields\Exercise as Fields;

class CardioExerciseConfig extends AbstractConfig
{
    public function __construct(
        public string $mode = 'distance',
        public int $distance = 0,
        public int $duration = 0,
        public int $pace = 0,
        public int $watts = 0,
    ) {}

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
