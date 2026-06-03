<?php

namespace Coda\Cms\Form\Fields;

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
        $this->optionView = 'cms::form.options.color';
    }
}
