<?php

namespace App\Data\Exercise\Settings;

use Coda\Cms\Form\Fields;

class PreviewSetting extends AbstractSetting
{
    public function __construct(
        public int $weeks = 1,
        public int $sessionsPerWeek = 1,
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
    ) {
        $this->measuredReps ??= 1;
        $this->measuredWeight ??= 50;
        $this->targetGoal ??= 10;
    }

    public static function fields(array $data = []): array
    {
        $fields = [
            Fields\Number::make('weeks')
                ->label('Weeks')
                ->min(1)
                ->max(12)
                ->step(1)
                ->default(1)
                ->suffix('week(s)'),
            Fields\Number::make('sessionsPerWeek')
                ->label('Sessions Per Week')
                ->min(1)
                ->max(7)
                ->step(1)
                ->default(1)
                ->suffix('session(s)'),
        ];

        $hasAutomaticWeight = in_array('weight', $data['config']['settings'] ?? [])
            && ($data['config']['weight']['mode'] ?? 'manual') === 'automatic';

        if ($hasAutomaticWeight) {
            $fields = array_merge($fields, WeightProgressionSetting::fields());
        }

        return $fields;
    }
}
