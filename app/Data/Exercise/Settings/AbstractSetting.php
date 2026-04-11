<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\CellInputMeta;
use Coda\Cms\Data\AbstractData;
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
}
