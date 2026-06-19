<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\DropSet;
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
        $pattern = DropSet::isEnabled($config)
            ? DropSet::commaPattern('duration')
            : self::DURATION_PATTERN;

        if ($unit === 'mm:ss') {
            return new CellInputMeta(
                inputType: 'text',
                maxlength: 15,
                pattern: $pattern,
            );
        }

        return new CellInputMeta(
            inputType: 'text',
            inputStep: '1',
            maxlength: 15,
            min: 0,
            pattern: $pattern,
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

    public static function fields(array $data = []): array
    {
        $dropSet = DropSet::isEnabled($data['config'] ?? $data);

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
                ->rules(fn (array $data): array => [
                    'nullable',
                    'regex:/^'.($dropSet ? DropSet::commaPattern('duration') : self::DURATION_PATTERN).'$/',
                    ...($dropSet ? [DropSet::partCountRule('duration', DropSet::expectedPartCount($data))] : []),
                ])
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

    public static function athleteRules(array $config = []): array
    {
        $pattern = DropSet::isEnabled($config)
            ? DropSet::commaPattern('duration')
            : self::DURATION_PATTERN;

        return [
            'required',
            'max:15',
            static function (string $attribute, mixed $value, \Closure $fail) use ($pattern): void {
                if (is_int($value) || is_float($value)) {
                    $value = (string) $value;
                }

                if (! is_string($value) || ! preg_match('/^'.$pattern.'$/', trim($value))) {
                    $fail('The :attribute field format is invalid.');
                }
            },
        ];
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

        if (DropSet::isEnabled($config)) {
            return static::normalizeDropSetValue($value, $unit);
        }

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

        if (DropSet::isEnabled($config) || (is_string($value) && str_contains($value, ','))) {
            $parts = static::dropSetParts($value, $unit);

            if ($parts !== null) {
                return implode(',', array_map(fn (int $part): string => static::formatDurationPart($part, $unit), $parts));
            }
        }

        $duration = SplitDuration::parse($value, $unit);

        if ($duration !== null) {
            return $duration->display();
        }

        return parent::formatAthleteValue($value, $unit, $config);
    }

    public static function athleteValueType(mixed $value, array $config = []): ?string
    {
        if (DropSet::isEnabled($config) && is_string($value) && str_contains($value, ',')) {
            return 'string';
        }

        if (is_string($value) && str_contains($value, '_')) {
            return 'string';
        }

        $unit = static::normalizeUnit($config['unit'] ?? 'seconds');
        $duration = SplitDuration::parse($value, $unit);

        if ($duration !== null && ! $duration->isSplit()) {
            return 'int';
        }

        return parent::athleteValueType($value, $config);
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        $unit = static::normalizeUnit($config['unit'] ?? 'seconds');

        $dropParts = static::dropSetParts($value, $unit);

        if ($dropParts !== null) {
            return [
                'kind' => 'duration',
                'format' => 'drop_set',
                'display' => implode(',', array_map(fn (int $part): string => static::formatDurationPart($part, $unit), $dropParts)),
                'unit' => $unit,
                'seconds' => array_sum($dropParts),
                'parts' => $dropParts,
                'is_bilateral' => false,
            ];
        }

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

    private static function normalizeDropSetValue(mixed $value, string $unit): mixed
    {
        $parts = static::dropSetParts($value, $unit);

        if ($parts === null) {
            return $value;
        }

        if ($unit === 'mm:ss') {
            return implode(',', $parts);
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * @return list<int>|null
     */
    private static function dropSetParts(mixed $value, string $unit): ?array
    {
        if (! is_string($value) || ! preg_match('/^'.DropSet::commaPattern('duration').'$/', trim($value))) {
            return null;
        }

        $parts = [];

        foreach (explode(',', trim($value)) as $part) {
            if (str_contains($part, ':')) {
                [$minutes, $seconds] = array_pad(explode(':', $part, 2), 2, '0');
                $parts[] = ((int) $minutes * 60) + (int) $seconds;

                continue;
            }

            $numeric = (int) $part;
            $parts[] = $unit === 'minutes' ? $numeric * 60 : $numeric;
        }

        return $parts;
    }

    private static function formatDurationPart(int $part, string $unit): string
    {
        if ($unit === 'mm:ss') {
            return sprintf('%d:%02d', intdiv($part, 60), $part % 60);
        }

        if ($unit === 'minutes') {
            return (string) intdiv($part, 60);
        }

        return (string) $part;
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
