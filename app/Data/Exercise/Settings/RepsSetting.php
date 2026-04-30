<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use App\Form\Fields\Exercise\ApplyPerField;
use App\Form\Fields\Reps;
use Coda\FormKit\Fields;

class RepsSetting extends AbstractSetting
{
    public function __construct(
        public string $mode = 'manual',
        public string|int|null $default = 10,
        public ?int $stepDownInterval = 2,
        public ?int $decrement = 2,
        public ?int $minimum = 1,
        public ?string $label = '',
        public string $applyPer = 'session',
    ) {}

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta(
            inputType: 'text',
            maxlength: 7,
            pattern: '\d+(_\d+)?',
        );
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if ($this->default === null || $this->default === '') {
            return [];
        }

        return [
            ['label' => $this->default.' reps', 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function fields(): array
    {
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
                ->default(10),
            Fields\Number::make('stepDownInterval')
                ->label('Step Down Interval')
                ->default(2)
                ->min(0)
                ->suffix('week(s)')
                ->show('mode == "automatic"'),
            Fields\Number::make('decrement')
                ->label('Rep Decrement')
                ->default(2)
                ->min(0)
                ->step(1)
                ->suffix('rep(s)')
                ->show('mode == "automatic"'),
            Fields\Number::make('minimum')
                ->label('Minimum Reps')
                ->default(1)
                ->min(1)
                ->step(1)
                ->suffix('rep(s)')
                ->show('mode == "automatic"'),
            Fields\Text::make('label')
                ->label('Label')
                ->placeholder('Reps')
                ->default(''),
            ApplyPerField::make()
                ->show('mode == "manual"'),
        ];
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
            ];
        }

        if (! is_string($value) || ! preg_match('/^\d+(?:_\d+)+$/', $value)) {
            return null;
        }

        $parts = array_map('intval', explode('_', $value));

        return [
            'kind' => 'reps',
            'format' => 'split',
            'display' => $value,
            'total' => array_sum($parts),
            'parts' => $parts,
            'is_bilateral' => count($parts) === 2,
        ];
    }
}
