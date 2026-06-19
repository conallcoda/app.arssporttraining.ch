<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\DropSet;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Form\Fields\Weight;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Field;
use Coda\FormKit\Fields;

class WeightSetting extends AbstractSetting
{
    public function __construct(
        public string $mode = 'manual',
        public ?int $oneRepMaxModifier = 100,
        public int|float|string|null $default = 5,
        public string $applyPer = ApplyPerScope::FORM_SET,
    ) {}

    public static function unitLabel(): string
    {
        return 'kg';
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        if (DropSet::isEnabled($config)) {
            return new CellInputMeta(
                inputType: 'text',
                inputStep: '0.5',
                maxlength: 31,
                min: 0,
                pattern: DropSet::commaPattern('weight'),
            );
        }

        return new CellInputMeta(
            inputType: 'number',
            inputStep: '0.5',
            min: 0,
        );
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->mode === 'automatic') {
            if ($this->oneRepMaxModifier === null) {
                return [];
            }

            return [
                ['label' => $this->oneRepMaxModifier.'%', 'modalField' => static::fieldsetKey()],
            ];
        }

        if ($this->default === null) {
            return [];
        }

        return [
            ['label' => static::formatAthleteValue($this->default).'kg', 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(array $data = []): array
    {
        if (DropSet::isEnabled($data['config'] ?? $data)) {
            return [
                Fields\Text::make('default')
                    ->label('Default Weight')
                    ->default('6,5,4')
                    ->maxLength(31)
                    ->suffix('kg')
                    ->rules(fn (array $data): array => [
                        'nullable',
                        'regex:/^'.DropSet::commaPattern('weight').'$/',
                        DropSet::partCountRule('weight', DropSet::expectedPartCount($data)),
                    ]),
                ApplyPerField::make(),
            ];
        }

        return [
            Fields\RadioSegmented::make('mode')
                ->label('Mode')
                ->options([
                    'automatic' => 'Automatic',
                    'manual' => 'Manual',
                ])
                ->default('manual')
                ->live(),
            Fields\Percentage::make('oneRepMaxModifier')
                ->label('1RM Modifier')
                ->default(100)
                ->rules('nullable|integer|min:0')
                ->show('mode == "automatic"'),
            Weight::make('default')
                ->label('Default Weight')
                ->default(5)
                ->rules('nullable|numeric|min:0')
                ->show('mode == "manual"'),
            ApplyPerField::make()
                ->show('mode == "manual"'),
        ];
    }

    public static function athleteField(string $name, array $config = []): Field
    {
        if (! DropSet::isEnabled($config)) {
            return parent::athleteField($name, $config);
        }

        return Fields\Text::make($name)
            ->label(static::athleteLabel($config))
            ->maxLength(31)
            ->suffix(static::resolveUnitLabel($config) ?? '')
            ->rules(static::athleteRules($config));
    }

    public static function normalizeAthleteValue(mixed $value, array $config = []): mixed
    {
        if (! DropSet::isEnabled($config)) {
            return parent::normalizeAthleteValue($value, $config);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    public static function athleteValueType(mixed $value, array $config = []): ?string
    {
        if (DropSet::isEnabled($config) && DropSet::weightParts($value) !== null) {
            return 'string';
        }

        return parent::athleteValueType($value, $config);
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        $parts = DropSet::weightParts($value);

        if ($parts === null) {
            return null;
        }

        return [
            'kind' => 'weight',
            'format' => 'drop_set',
            'display' => DropSet::displayWeight($parts),
            'unit' => static::resolveUnitLabel($config),
            'parts' => $parts,
        ];
    }

    public static function formatAthleteValue(mixed $value, ?string $unit = null, array $config = []): ?string
    {
        $parts = DropSet::weightParts($value);

        if ($parts !== null) {
            return DropSet::displayWeight($parts);
        }

        return parent::formatAthleteValue($value, $unit, $config);
    }
}
