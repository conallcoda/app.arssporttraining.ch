<?php

namespace App\Form\Fields\Training\Program;

use App\Cms\Form\Fields\Select;

class Color extends Select
{
    public const DEFAULT_COLOR = 'blue';

    public const AVAILABLE_COLORS = [
        'blue' => 'Blue',
        'green' => 'Green',
        'emerald' => 'Emerald',
        'teal' => 'Teal',
        'cyan' => 'Cyan',
        'sky' => 'Sky',
        'indigo' => 'Indigo',
        'violet' => 'Violet',
        'purple' => 'Purple',
        'pink' => 'Pink',
        'rose' => 'Rose',
        'red' => 'Red',
        'orange' => 'Orange',
        'amber' => 'Amber',
        'yellow' => 'Yellow',
        'lime' => 'Lime',
    ];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Color';
        $this->options = self::AVAILABLE_COLORS;
        $this->default = self::DEFAULT_COLOR;
    }
}
