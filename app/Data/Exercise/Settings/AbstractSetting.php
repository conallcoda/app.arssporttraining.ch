<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Field;
use Coda\FormKit\Fields;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Form;
use Illuminate\Support\Str;

abstract class AbstractSetting extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static function getName(): string
    {
        $name = str_replace('Setting', '', class_basename(static::class));

        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
    }

    public static function unitLabel(): string|array|null
    {
        return null;
    }

    public static function resolveUnitLabel(array $data = []): ?string
    {
        $label = static::unitLabel();

        if ($label === null) {
            return null;
        }

        if (is_string($label)) {
            return $label;
        }

        $unit = $data['unit'] ?? array_key_first($label);

        return $label[$unit] ?? reset($label);
    }

    public static function inputMeta(array $config = []): CellInputMeta
    {
        return new CellInputMeta;
    }

    public static function fields(): array
    {
        return [];
    }

    public static function view(): ?string
    {
        return null;
    }

    public static function fieldsetKey(): string
    {
        return Str::snake(static::getName());
    }

    /** @return list<array{label: string, modalField: string}> */
    public function badges(): array
    {
        if (! property_exists($this, 'default')) {
            return [];
        }

        $default = $this->default;

        if ($default === null || $default === '') {
            return [];
        }

        $unitLabel = static::resolveUnitLabel($this->toArray()) ?? '';

        return [
            ['label' => $default.$unitLabel, 'modalField' => static::fieldsetKey()],
        ];
    }

    public static function getForm(): Form|array
    {
        return Form::make()
            ->fieldset(static::getName(), static::fields(), view: static::view());
    }

    public static function athleteLabel(array $config = []): string
    {
        $label = $config['label'] ?? null;

        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        return static::getName();
    }

    public static function athleteField(string $name, array $config = []): Field
    {
        $meta = static::inputMeta($config);
        $field = match (static::athleteFieldType($config)) {
            'number' => Fields\Number::make($name),
            'duration' => Fields\Duration::make($name)->defaultUnit($config['unit'] ?? 'seconds'),
            'textarea' => Fields\Textarea::make($name),
            default => Fields\Text::make($name),
        };

        $field->label(static::athleteLabel($config));

        if (method_exists($field, 'min') && $meta->min !== null) {
            $field->min($meta->min);
        }

        if (method_exists($field, 'max') && $meta->max !== null) {
            $field->max($meta->max);
        }

        if (property_exists($field, 'step') && $meta->inputStep !== '') {
            $field->step = $meta->inputStep;
        }

        if (property_exists($field, 'maxLength') && $meta->maxlength !== null) {
            $field->maxLength = $meta->maxlength;
        }

        if (property_exists($field, 'mask') && $meta->mask) {
            $field->mask($meta->mask);
        }

        if (property_exists($field, 'inputType')) {
            $field->inputType = $meta->inputType;
        }

        if (method_exists($field, 'suffix')) {
            $unit = static::resolveUnitLabel($config);

            if ($unit) {
                $field->suffix($unit);
            }
        }

        $rules = static::athleteRules($config);
        if ($rules !== []) {
            $field->rules($rules);
        }

        return $field;
    }

    /**
     * @return array<int, string>
     */
    public static function athleteRules(array $config = []): array
    {
        $meta = static::inputMeta($config);
        $rules = ['required'];

        if ($meta->inputType === 'number') {
            $rules[] = 'numeric';

            if ($meta->min !== null) {
                $rules[] = 'min:'.$meta->min;
            }

            if ($meta->max !== null) {
                $rules[] = 'max:'.$meta->max;
            }

            return $rules;
        }

        $rules[] = 'string';

        if ($meta->maxlength !== null) {
            $rules[] = 'max:'.$meta->maxlength;
        }

        if ($meta->pattern) {
            $rules[] = 'regex:/^'.$meta->pattern.'$/';
        }

        return $rules;
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

        $meta = static::inputMeta($config);

        if (static::athleteFieldType($config) === 'duration' && is_string($value) && str_contains($value, ':')) {
            [$minutes, $seconds] = array_pad(explode(':', $value, 2), 2, '0');

            return ((int) $minutes * 60) + (int) $seconds;
        }

        if ($meta->inputType === 'number' && is_numeric($value)) {
            $step = $meta->inputStep;

            if (is_string($step) && str_contains($step, '.')) {
                return round((float) $value, 3);
            }

            return (int) round((float) $value);
        }

        return is_string($value) ? trim($value) : $value;
    }

    public static function formatAthleteValue(mixed $value, ?string $unit = null, array $config = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (($config['unit'] ?? null) === 'mm:ss' && is_numeric($value)) {
            $totalSeconds = (int) $value;
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds % 60;

            return sprintf('%d:%02d', $minutes, $seconds);
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    public static function athleteValueType(mixed $value, array $config = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return 'json';
        }

        $meta = static::inputMeta($config);

        if ($meta->inputType === 'number') {
            if (is_float($value) || (is_string($value) && is_numeric($value) && str_contains($value, '.'))) {
                return 'decimal';
            }

            return 'int';
        }

        if (($config['unit'] ?? null) === 'mm:ss' && is_numeric($value)) {
            return 'int';
        }

        return 'string';
    }

    public static function athleteCanonicalValue(mixed $value, array $config = []): ?array
    {
        return null;
    }

    protected static function athleteFieldType(array $config = []): string
    {
        if (($config['unit'] ?? null) === 'mm:ss') {
            return 'duration';
        }

        return static::inputMeta($config)->inputType === 'number' ? 'number' : 'text';
    }
}
