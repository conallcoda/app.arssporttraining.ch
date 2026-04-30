<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Form\Fields\HeartRateZone;

class HeartRateZoneSetting extends AbstractSetting
{
    public function __construct(
        public ?string $default = '2',
        public string $applyPer = 'session',
    ) {}

    public static function unitLabel(): string
    {
        return 'zone';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 3,
            pattern: '[0-4](-[0-4])?',
        );
    }

    public function badges(): array
    {
        if ($this->default === null || $this->default === '') {
            return [];
        }

        return [
            ['label' => 'Zone '.$this->default, 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            HeartRateZone::make('default')
                ->label('Default Zone')
                ->default('2'),
            ApplyPerField::make(),
        ];
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value))) {
            $numeric = (int) $value;

            return [
                'kind' => 'heart_rate_zone',
                'format' => 'scalar',
                'display' => (string) $value,
                'value' => $numeric,
                'min' => $numeric,
                'max' => $numeric,
            ];
        }

        if (! is_string($value) || ! preg_match('/^(?<min>\d+)-(?<max>\d+)$/', $value, $matches)) {
            return null;
        }

        return [
            'kind' => 'heart_rate_zone',
            'format' => 'range',
            'display' => $value,
            'min' => (int) $matches['min'],
            'max' => (int) $matches['max'],
        ];
    }

    public static function formatAthleteValue(mixed $value, ?string $unit = null, array $config = []): ?string
    {
        $formatted = parent::formatAthleteValue($value, $unit, $config);

        return $formatted === null ? null : 'Zone '.$formatted;
    }
}
