<?php

namespace App\Form\Fields\Training\Program;

use Coda\Cms\Form\Fields\Color as CmsColor;
use Coda\Cms\Support\ColorPalette;

class Color extends CmsColor
{
    public const DEFAULT_COLOR = 'blue';

    public const AVAILABLE_COLORS = ColorPalette::COLORS;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->default = self::DEFAULT_COLOR;
    }
}
