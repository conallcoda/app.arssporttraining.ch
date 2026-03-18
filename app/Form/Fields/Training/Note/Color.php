<?php

namespace App\Form\Fields\Training\Note;

use Coda\Cms\Form\Fields\Select;
use Coda\Cms\Support\ColorPalette;

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
