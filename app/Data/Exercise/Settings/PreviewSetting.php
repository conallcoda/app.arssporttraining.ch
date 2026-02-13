<?php

namespace App\Data\Exercise\Settings;

use App\Cms\Form\Fields;

class PreviewSetting
{
    public static function fields(array $data = []): array
    {
        $fields = [
            Fields\Number::make('weeks')
                ->label('Weeks')
                ->min(1)
                ->max(12)
                ->step(1)
                ->default(5)
                ->suffix('week(s)')
                ->live(),
            Fields\Number::make('sessionsPerWeek')
                ->label('Sessions Per Week')
                ->min(1)
                ->max(7)
                ->step(1)
                ->default(1)
                ->suffix('session(s)')
                ->live(),
        ];

        $hasAutomaticWeight = in_array('weight', $data['config']['settings'] ?? [])
            && ($data['config']['weight']['mode'] ?? 'manual') === 'automatic';

        if ($hasAutomaticWeight) {
            $fields = array_merge($fields, WeightProgressionSetting::fields());
        }

        return $fields;
    }
}
