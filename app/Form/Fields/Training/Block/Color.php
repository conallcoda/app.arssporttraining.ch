<?php

namespace App\Form\Fields\Training\Block;

use Coda\Cms\Support\ColorPalette;
use Coda\FormKit\Fields\Select;

class Color extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Color';
        $this->options = ColorPalette::COLORS;
        $this->variant = 'listbox';
        $this->optionView = 'form.options.color';
    }
}
