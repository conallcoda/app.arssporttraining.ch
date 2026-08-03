<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasNumericConstraints;
use Coda\FormKit\Concerns\HasSuffix;
use Coda\FormKit\Concerns\HasTicks;
use Coda\FormKit\Field;

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
