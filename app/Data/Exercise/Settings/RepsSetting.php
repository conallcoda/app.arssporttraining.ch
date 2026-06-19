<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\DropSet;
use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Form\Fields\Reps;
use App\Support\Training\ApplyPerScope;
use Coda\FormKit\Field;
use Coda\FormKit\Fields;

class RepsSetting extends AbstractSetting
{
    public const BILATERAL_EXECUTION_CONSECUTIVE = 'consecutive';

    public const BILATERAL_EXECUTION_ALTERNATING = 'alternating';

    private const PLANNED_PATTERN = '\d+(?:_\d+|-\d+)?';

    private const ATHLETE_PATTERN = '\d+(?:_\d+)?';

    private const DROP_SET_MAX_LENGTH = 31;

    public function __construct(
        public string $mode = 'manual',
        public string|int|null $default = 10,
        public ?int $stepDownInterval = 2,
        public ?int $decrement = 2,
        public ?int $minimum = 1,
        public ?string $label = '',
        public string $bilateralExecution = self::BILATERAL_EXECUTION_CONSECUTIVE,
        public string $applyPer = ApplyPerScope::FORM_SET,
    ) {}

    public static function inputMeta(array $config = []): CellInputMeta
    {
        if (DropSet::isEnabled($config)) {
            return new CellInputMeta(
                inputType: 'text',
                maxlength: self::DROP_SET_MAX_LENGTH,
                pattern: DropSet::repsPattern(),
            );
        }

        return new CellInputMeta(
            inputType: 'text',
            maxlength: 7,
            pattern: self::PLANNED_PATTERN,
        );
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->default === null || $this->default === '') {
            return [];
        }

        return [
            ['label' => static::formatAthleteValue($this->default).' reps', 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(array $data = []): array
    {
        if (DropSet::isEnabled($data['config'] ?? $data)) {
            return [
                Reps::make('default')
                    ->label('Default Reps')
                    ->default('12,12,12')
                    ->maxLength(self::DROP_SET_MAX_LENGTH)
                    ->rules(['required', 'regex:/^'.DropSet::repsPattern().'$/'])
                    ->live(),
                Fields\Text::make('label')
                    ->label('Label')
                    ->placeholder('Reps')
                    ->default(''),
                ApplyPerField::make(ApplyPerScope::FORM_SET),
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
            Reps::make('default')
                ->label('Default Reps')
                ->default(10)
                ->rules(fn (array $data): array => static::defaultRules($data))
                ->live(),
            Fields\Number::make('stepDownInterval')
                ->label('Step Down Interval')
                ->default(2)
                ->min(0)
                ->rules('nullable|integer|min:0')
                ->suffix('week(s)')
                ->show('mode == "automatic"'),
            Fields\Number::make('decrement')
                ->label('Rep Decrement')
                ->default(2)
                ->min(0)
                ->step(1)
                ->rules('nullable|integer|min:0')
                ->suffix('rep(s)')
                ->show('mode == "automatic"'),
            Fields\Number::make('minimum')
                ->label('Minimum Reps')
                ->default(1)
                ->min(1)
                ->step(1)
                ->rules('nullable|integer|min:1')
                ->suffix('rep(s)')
                ->show('mode == "automatic"'),
            Fields\Text::make('label')
                ->label('Label')
                ->placeholder('Reps')
                ->default(''),
            ApplyPerField::make(ApplyPerScope::FORM_SET)
                ->show('mode == "manual"'),
        ];
    }

    public static function athleteField(string $name, array $config = []): Field
    {
        return Reps::make($name)
            ->label(static::athleteLabel($config))
            ->rules(static::athleteRules($config));
    }

    public static function athleteRules(array $config = []): array
    {
        if (DropSet::isEnabled($config)) {
            return [
                'required',
                static function (string $attribute, mixed $value, \Closure $fail) use ($config): void {
                    if (! is_string($value)) {
                        $fail('The :attribute field format is invalid.');

                        return;
                    }

                    $parts = static::athleteDropSetParts($value, $config);

                    if ($parts === null) {
                        $fail('The :attribute field format is invalid.');
                    }
                },
            ];
        }

        return ['required', 'regex:/^'.self::ATHLETE_PATTERN.'$/'];
    }

    public static function defaultRules(array $data = []): array
    {
        if (DropSet::isEnabled($data['config'] ?? $data)) {
            return ['required', 'regex:/^'.DropSet::repsPattern().'$/'];
        }

        $requiresConcreteReps = static::requiresConcretePlanningReps($data);

        return [
            $requiresConcreteReps ? 'required' : 'nullable',
            'regex:/^'.($requiresConcreteReps ? self::ATHLETE_PATTERN : self::PLANNED_PATTERN).'$/',
        ];
    }

    public static function requiresConcretePlanningReps(array $data = []): bool
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : $data;
        $settings = $config['settings'] ?? [];
        $repsMode = (string) ($config['reps']['mode'] ?? $data['mode'] ?? 'manual');
        $weightMode = (string) ($config['weight']['mode'] ?? 'manual');

        if (DropSet::isEnabled($config)) {
            return false;
        }

        $hasAutomaticWeight = in_array('weight', $settings, true) && $weightMode === 'automatic';

        return $repsMode === 'automatic' || $hasAutomaticWeight;
    }

    public static function isValidPlanningValue(mixed $value, array $data = []): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        $requiresConcreteReps = static::requiresConcretePlanningReps($data);

        if ($value === '') {
            return ! $requiresConcreteReps;
        }

        if (DropSet::isEnabled(is_array($data['config'] ?? null) ? $data['config'] : $data)) {
            return (bool) preg_match('/^'.DropSet::repsPattern().'$/', $value);
        }

        return (bool) preg_match('/^'.($requiresConcreteReps ? self::ATHLETE_PATTERN : self::PLANNED_PATTERN).'$/', $value);
    }

    public static function normalizeAthleteValue(mixed $value, array $config = []): mixed
    {
        $value = parent::normalizeAthleteValue($value, $config);

        if (! DropSet::isEnabled($config)) {
            return $value;
        }

        $parts = static::athleteDropSetParts($value, $config);

        return $parts === null ? DropSet::normalizeRepsValue($value) : implode(',', $parts);
    }

    /**
     * @return list<int>|null
     */
    private static function athleteDropSetParts(mixed $value, array $config): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $parts = DropSet::repsParts($value);

        if ($parts === null) {
            return null;
        }

        $expected = DropSet::expectedPartCount($config);

        if ($expected === null) {
            return $parts;
        }

        if (count($parts) > $expected) {
            return null;
        }

        return array_pad($parts, $expected, 0);
    }

    public static function normalizeBilateralExecution(?string $execution): string
    {
        return self::BILATERAL_EXECUTION_ALTERNATING;
    }

    public static function bilateralExecutionHint(?string $execution): string
    {
        return match (static::normalizeBilateralExecution($execution)) {
            self::BILATERAL_EXECUTION_ALTERNATING => 'Alternate sides each rep, for example 1 left, 1 right, repeat until both sides are complete.',
            default => 'Complete all reps on one side first, then all reps on the other, for example 6 left, then 6 right.',
        };
    }

    public static function requiresAthleteSpecificValue(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || $value === '-'
            || $value === '—'
            || (is_string($value) && preg_match('/^\d+-\d+$/', $value));
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value))) {
            $total = (int) $value;

            return [
                'kind' => 'reps',
                'format' => 'scalar',
                'display' => (string) $value,
                'total' => $total,
                'parts' => [$total],
                'is_bilateral' => false,
                'bilateral_execution' => null,
            ];
        }

        if (is_string($value) && preg_match('/^(?<min>\d+)-(?<max>\d+)$/', $value, $matches)) {
            return [
                'kind' => 'reps',
                'format' => 'range',
                'display' => $value,
                'min' => (int) $matches['min'],
                'max' => (int) $matches['max'],
                'is_bilateral' => false,
                'bilateral_execution' => null,
            ];
        }

        $dropParts = DropSet::repsParts($value);

        if ($dropParts !== null) {
            return [
                'kind' => 'reps',
                'format' => 'drop_set',
                'display' => DropSet::displayReps($dropParts),
                'total' => array_sum($dropParts),
                'parts' => $dropParts,
                'is_bilateral' => false,
                'bilateral_execution' => null,
            ];
        }

        if (! is_string($value) || ! preg_match('/^\d+(?:_\d+)+$/', $value)) {
            return null;
        }

        $parts = array_map('intval', explode('_', $value));

        $isBilateral = count($parts) === 2;

        return [
            'kind' => 'reps',
            'format' => 'split',
            'display' => static::formatAthleteValue($value),
            'total' => array_sum($parts),
            'parts' => $parts,
            'is_bilateral' => $isBilateral,
            'bilateral_execution' => $isBilateral
                ? static::normalizeBilateralExecution(null)
                : null,
        ];
    }

    public static function formatAthleteValue(mixed $value, ?string $unit = null, array $config = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dropParts = DropSet::repsParts($value);

        if ($dropParts !== null) {
            return DropSet::displayReps($dropParts);
        }

        if (! is_string($value) || ! preg_match('/^(?<left>\d+)_(?<right>\d+)$/', trim($value), $matches)) {
            return parent::formatAthleteValue($value, $unit, $config);
        }

        return $matches['left'].'L_'.$matches['right'].'R';
    }
}
