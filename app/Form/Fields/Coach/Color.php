<?php

namespace App\Form\Fields\Coach;

use Coda\Cms\Form\Fields\Select;
use Coda\Cms\Support\ColorPalette;

class Color extends Select
{
    public const DEFAULT_COLOR = 'blue';

    public const AVAILABLE_COLORS = ColorPalette::COLORS;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Color';
        $this->options = ColorPalette::COLORS;
        $this->default = self::DEFAULT_COLOR;
        $this->variant = 'listbox';
        $this->optionView = 'form.options.color';
    }
}
