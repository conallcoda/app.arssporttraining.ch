<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Livewire\Test\Data\Preview\CellInputMeta;

abstract class AbstractSetting extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static function getName()
    {
        return str_replace('Setting', '', class_basename(static::class));
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

    public static function getForm(): Form|array
    {
        return Form::make()
            ->fieldset(static::getName(), static::fields(), view: static::view());
    }
}
