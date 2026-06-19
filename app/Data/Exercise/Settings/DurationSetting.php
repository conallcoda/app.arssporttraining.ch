<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Data\Exercise\SplitDuration;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Field;
use Coda\FormKit\Fields;

class DurationSetting extends AbstractSetting
{
    private const DURATION_PATTERN = '(?:\d+|\d{1,3}:[0-5]\d)(?:_(?:\d+|\d{1,3}:[0-5]\d))?';

    public function __construct(
        public string $unit = 'seconds',
        public int|string|null $default = 60,
        public string $applyPer = ApplyPerScope::FORM_SET,
    ) {}

    public static function unitLabel(): array
    {
        return ['seconds' => 's', 'minutes' => 'm', 'mm:ss' => 'mm:ss'];
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        $unit = static::normalizeUnit($config['unit'] ?? 'seconds');

        if ($unit === 'mm:ss') {
            return new CellInputMeta(
                inputType: 'text',
                maxlength: 15,
                pattern: self::DURATION_PATTERN,
            );
        }

        return new CellInputMeta(
            inputType: 'text',
            inputStep: '1',
            maxlength: 15,
            min: 0,
            pattern: self::DURATION_PATTERN,
        );
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->default === null || $this->default === '' || $this->default === 0 || $this->default === '0:00') {
            return [];
        }

        $formatted = static::formatAthleteValue($this->default, null, $this->toArray()) ?? (string) $this->default;
        $unitLabel = $this->unit === 'mm:ss' || str_contains($formatted, '_') ? '' : (static::resolveUnitLabel($this->toArray()) ?? '');

        return [
            ['label' => $formatted.$unitLabel, 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
        return [
            Fields\RadioSegmented::make('unit')
                ->label('Unit')
                ->options([
                    'seconds' => 'Seconds',
                    'minutes' => 'Minutes',
                    'mm:ss' => 'mm:ss',
                ])
                ->default('seconds')
                ->live(),
            Fields\Text::make('default')
                ->label('Default Duration')
                ->default(60)
                ->maxLength(15)
                ->suffixMap([
                    'seconds' => 's',
                    'minutes' => 'm',
                    'mm:ss' => 'mm:ss',
                ])
                ->rules(['nullable', 'regex:/^'.self::DURATION_PATTERN.'$/'])
                ->live(),
            ApplyPerField::make(),
        ];
    }

    public static function athleteField(string $name, array $config = []): Field
    {
        return Fields\Text::make($name)
            ->label(static::athleteLabel($config))
            ->maxLength(15)
            ->suffix(static::resolveUnitLabel($config) ?? '')
            ->rules(static::athleteRules($config));
    }

    public static function normalizeAthleteValue(mixed $value, array $config = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        $unit = static::normalizeUnit($config['unit'] ?? 'seconds');
        $duration = SplitDuration::parse($value, $unit);

        if ($duration !== null) {
            return $duration->storageValue();
        }

        return parent::normalizeAthleteValue($value, $config);
    }

    public static function formatAthleteValue(mixed $value, ?string $unit = null, array $config = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $unit = static::normalizeUnit($config['unit'] ?? $unit ?? 'seconds');
        $duration = SplitDuration::parse($value, $unit);

        if ($duration !== null) {
            return $duration->display();
        }

        return parent::formatAthleteValue($value, $unit, $config);
    }

    public static function athleteValueType(mixed $value, array $config = []): ?string
    {
        if (is_string($value) && str_contains($value, '_')) {
            return 'string';
        }

        return parent::athleteValueType($value, $config);
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        $unit = static::normalizeUnit($config['unit'] ?? 'seconds');
        $duration = SplitDuration::parse($value, $unit);

        if ($duration === null) {
            return null;
        }

        return [
            'kind' => 'duration',
            'format' => $duration->isSplit() ? 'split' : 'scalar',
            'display' => $duration->display(),
            'unit' => $unit,
            'seconds' => $duration->isSplit() ? array_sum($duration->parts) : $duration->parts[0],
            'parts' => $duration->parts,
            'is_bilateral' => $duration->isSplit(),
        ];
    }

    private static function normalizeUnit(?string $unit): string
    {
        return match ($unit) {
            'm' => 'minutes',
            's' => 'seconds',
            'mm:ss' => 'mm:ss',
            default => $unit ?: 'seconds',
        };
    }
}
