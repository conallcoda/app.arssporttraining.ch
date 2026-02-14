<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasNumericConstraints;
use Coda\Cms\Form\Concerns\HasSuffix;
use Coda\Cms\Form\Concerns\HasTicks;
use Coda\Cms\Form\Field;

class Slider extends Field
{
    use HasNumericConstraints;
    use HasSuffix;
    use HasTicks;

    public string $type = 'slider';

    public function __construct(string $name)
    {
        parent::__construct($name);
    }
}
